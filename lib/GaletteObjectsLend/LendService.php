<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Preferences as CorePreferences;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Repository\Objects;
use GaletteObjectsLend\Repository\Rents;
use GaletteObjectsLend\Repository\Status;
use Throwable;

/**
 * Lends: take, give back and status changes
 *
 * This is the only place where rents are closed and opened, and where
 * the object current rent is set.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendService
{
    /**
     * Constructor
     *
     * @param Db              $zdb         Database instance
     * @param CorePreferences $preferences Preferences
     * @param Login           $login       Logged in instance
     * @param LendPreferences $lendsprefs  Plugin preferences
     */
    public function __construct(
        private Db $zdb,
        private CorePreferences $preferences,
        private Login $login,
        private LendPreferences $lendsprefs
    ) {
    }

    /**
     * Load an object with all information lend operations rely on
     *
     * @param int $id Object ID
     */
    public function getObject(int $id): LendObject
    {
        return (new Objects($this->zdb, $this->preferences, $this->login, $this->lendsprefs))->getWithCurrentRent($id);
    }

    /**
     * Can current user borrow objects?
     */
    public function canTake(): bool
    {
        return $this->isManager()
            || $this->lendsprefs->isEnabled(LendPreferences::ENABLE_MEMBER_RENT_OBJECT);
    }

    /**
     * Can current user give back an object?
     *
     * Staff and admins always can; members only when they are allowed
     * to borrow objects and hold the object.
     *
     * @param LendObject $object Object, loaded with getObject()
     */
    public function canGiveBack(LendObject $object): bool
    {
        if ($this->isManager()) {
            return true;
        }

        return $this->lendsprefs->isEnabled(LendPreferences::ENABLE_MEMBER_RENT_OBJECT)
            && $object->getIdAdh() !== null
            && $this->login->id == $object->getIdAdh();
    }

    /**
     * Can current user change the status of an object?
     */
    public function canChangeStatus(): bool
    {
        return $this->isManager();
    }

    /**
     * Is object available to be borrowed?
     *
     * @param LendObject $object Object, loaded with getObject()
     */
    public function isAvailable(LendObject $object): bool
    {
        return $object->getId() !== null
            && $object->isActive()
            && ($object->getRentId() === null || $object->inStock());
    }

    /**
     * Is object currently lent?
     *
     * @param LendObject $object Object, loaded with getObject()
     */
    public function isLent(LendObject $object): bool
    {
        return $object->getId() !== null
            && $object->getRentId() !== null
            && !$object->inStock();
    }

    /**
     * Borrow an object
     *
     * When contribution generation is enabled and the rent is not free,
     * the contribution is stored along with the rent; if it cannot be,
     * nothing is stored.
     *
     * @param LendObject $object        Object, loaded with getObject()
     * @param int        $status_id     Take away status
     * @param ?string    $date_forecast Expected return date
     * @param ?int       $member_id     Borrower; only staff may lend to someone else
     * @param ?float     $rent_price    Rent price; only staff may change it
     * @param ?int       $payment_type  Payment type of the contribution
     *
     * @return ?Contribution Generated contribution, if any
     *
     * @throws LendException
     */
    public function take(
        LendObject $object,
        int $status_id,
        ?string $date_forecast = null,
        ?int $member_id = null,
        ?float $rent_price = null,
        ?int $payment_type = null
    ): ?Contribution {
        if (!$this->canTake()) {
            $this->refuse(
                'Trying to borrow an object without appropriate rights!',
                $object,
                _T("You do not have rights to borrow objects!", "objectslend")
            );
        }

        if (
            !$this->isAvailable($object)
            || !$this->isAllowedStatus($status_id, $this->getStatuses()->getActiveTakeAwayStatuses())
        ) {
            $this->refuse(
                'Trying to borrow an unavailable object or with an invalid status!',
                $object,
                _T("This object cannot be borrowed.", "objectslend")
            );
        }

        if ($member_id === null || !$this->isManager()) {
            $member_id = $this->login->id;
        }

        if ($rent_price === null || !$this->isManager()) {
            $rent_price = $object->getRentPrice();
        }

        return $this->inTransaction(function () use ($object, $status_id, $member_id, $date_forecast, $rent_price, $payment_type) {
            $rent = $this->openRent($object, $status_id, $member_id, '', $date_forecast);

            if ($this->lendsprefs->isEnabled(LendPreferences::AUTO_GENERATE_CONTRIBUTION) && $rent_price > 0) {
                return $this->storeContribution($object, $rent, $rent_price, $payment_type);
            }
            return null;
        });
    }

    /**
     * Give back an object
     *
     * @param LendObject $object    Object, loaded with getObject()
     * @param int        $status_id In stock status
     *
     * @throws LendException
     */
    public function giveBack(LendObject $object, int $status_id): LendRent
    {
        if (!$this->canGiveBack($object)) {
            $this->refuse(
                'Trying to return an object without appropriate rights!',
                $object,
                _T("You do not have rights to return objects!", "objectslend")
            );
        }

        if (
            !$this->isLent($object)
            || !$this->isAllowedStatus($status_id, $this->getStatuses()->getActiveStockStatuses())
        ) {
            $this->refuse(
                'Trying to return an object that is not lent or with an invalid status!',
                $object,
                _T("This object cannot be returned.", "objectslend")
            );
        }

        return $this->inTransaction(
            fn() => $this->openRent($object, $status_id, null, '')
        );
    }

    /**
     * Change object status, whatever its current one
     *
     * @param LendObject $object    Object
     * @param int        $status_id New status
     * @param ?int       $member_id Member holding the object, if any
     * @param string     $comments  Comment on the closed rent
     *
     * @throws LendException
     */
    public function changeStatus(
        LendObject $object,
        int $status_id,
        ?int $member_id = null,
        string $comments = ''
    ): LendRent {
        if (!$this->canChangeStatus()) {
            $this->refuse(
                'Trying to change an object status without appropriate rights!',
                $object,
                _T("You do not have rights to change objects status!", "objectslend")
            );
        }

        $status = new LendStatus($this->zdb, $status_id);
        if ($object->getId() === null || $status->getId() === null || !$status->isActive()) {
            $this->refuse(
                'Trying to change an object status to an invalid one!',
                $object,
                _T("This status cannot be set.", "objectslend")
            );
        }

        return $this->inTransaction(
            fn() => $this->openRent($object, $status_id, $member_id, $comments)
        );
    }

    /**
     * Close current rents, open a new one and set it as the object current one
     *
     * @param LendObject $object        Object
     * @param int        $status_id     Status of the new rent
     * @param ?int       $member_id     Member of the new rent
     * @param string     $comments      Comment on closed rents
     * @param ?string    $date_forecast Expected return date
     */
    private function openRent(
        LendObject $object,
        int $status_id,
        ?int $member_id,
        string $comments,
        ?string $date_forecast = null
    ): LendRent {
        $object_id = (int)$object->getId();

        (new Rents($this->zdb))->closeAllForObject($object_id, $comments);

        $rent = new LendRent($this->zdb);
        $rent
            ->setObjectId($object_id)
            ->setStatusId($status_id)
            ->setAdherentId($member_id);
        if ($date_forecast !== null) {
            $rent->setDateForecast($date_forecast);
        }
        $rent->store();

        $update = $this->zdb->update(LEND_PREFIX . LendObject::TABLE)
            ->set([LendRent::PK => $rent->getId()])
            ->where([LendObject::PK => $object_id]);
        $this->zdb->execute($update);

        return $rent;
    }

    /**
     * Store contribution for a rent
     *
     * @param LendObject $object       Object
     * @param LendRent   $rent         Rent
     * @param float      $amount       Amount
     * @param ?int       $payment_type Payment type
     *
     * @throws LendException
     */
    private function storeContribution(
        LendObject $object,
        LendRent $rent,
        float $amount,
        ?int $payment_type
    ): Contribution {
        $info = str_replace(
            [
                '{NAME}',
                '{DESCRIPTION}',
                '{SERIAL_NUMBER}',
                '{PRICE}',
                '{RENT_PRICE}',
                '{WEIGHT}',
                '{DIMENSION}'
            ],
            [
                $object->getName(),
                $object->getDescription(),
                $object->getSerialNumber(),
                number_format($object->getPrice(), 2, ',', ' '),
                number_format($object->getRentPrice(), 2, ',', ' '),
                number_format($object->getWeight(), 3, ',', ' '),
                $object->getDimension()
            ],
            $this->lendsprefs->getContributionText()
        );

        $values = [
            'montant_cotis'         => $amount,
            ContributionsTypes::PK  => $this->lendsprefs->getContributionTypeId(),
            'date_enreg'            => date("Y-m-d"),
            'date_debut_cotis'      => date("Y-m-d"),
            'type_paiement_cotis'   => $payment_type,
            'info_cotis'            => $info,
            Adherent::PK            => $rent->getAdherentId()
        ];

        //borrower has already been checked: members can only borrow for themselves
        $contrib = new Contribution($this->zdb, $this->login);
        $contrib->setNoCheckLogin();
        $valid = $contrib->check($values, [], []);
        if ($valid !== true) {
            Analog::log(
                'Unable to generate contribution for object #' . $object->getId() . ': '
                . implode(' ', (array)$valid),
                Analog::ERROR
            );
            throw new LendException(
                _T("An error occurred while storing the contribution.", "objectslend")
            );
        }

        try {
            $contrib->store();
        } catch (Throwable $e) {
            Analog::log(
                'Unable to store contribution for object #' . $object->getId() . ': ' . $e->getMessage(),
                Analog::ERROR
            );
            throw new LendException(
                _T("An error occurred while storing the contribution.", "objectslend"),
                previous: $e
            );
        }

        return $contrib;
    }

    /**
     * Run callback in a transaction
     *
     * When caller already runs a transaction, it is up to it to roll back
     * on failure.
     *
     * @template T
     *
     * @param callable(): T $callback Callback
     *
     * @return T
     */
    private function inTransaction(callable $callback): mixed
    {
        $own_transaction = !$this->zdb->inTransaction();
        if ($own_transaction) {
            $this->zdb->beginTransaction();
        }
        try {
            $result = $callback();
            if ($own_transaction) {
                $this->zdb->commit();
            }
            return $result;
        } catch (Throwable $e) {
            //contribution may already have rolled back
            if ($own_transaction && $this->zdb->inTransaction()) {
                $this->zdb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Log and refuse operation
     *
     * @param string     $log     Log message
     * @param LendObject $object  Object
     * @param string     $message Message for the user
     *
     * @throws LendException
     */
    private function refuse(string $log, LendObject $object, string $message): never
    {
        Analog::log(
            $log . ' (Object ' . $object->getId() . ', user ' . $this->login->login . ')',
            Analog::WARNING
        );
        throw new LendException($message);
    }

    /**
     * Get statuses repository
     */
    private function getStatuses(): Status
    {
        return new Status($this->zdb, $this->preferences, $this->login);
    }

    /**
     * Is current user staff or admin?
     */
    private function isManager(): bool
    {
        return $this->login->isAdmin() || $this->login->isStaff();
    }

    /**
     * Is status part of allowed ones?
     *
     * @param int          $status_id Status ID
     * @param LendStatus[] $statuses  Allowed statuses
     */
    private function isAllowedStatus(int $status_id, array $statuses): bool
    {
        foreach ($statuses as $status) {
            if ($status->getId() === $status_id) {
                return true;
            }
        }
        return false;
    }
}
