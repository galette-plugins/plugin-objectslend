<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity;

use Analog\Analog;
use ArrayObject;
use Galette\Core\Db;

/**
 * Rents
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendRent
{
    public const string TABLE = 'rents';
    public const string PK = 'rent_id';

    /** @var array<string, string> */
    private array $fields = [
        'rent_id' => 'integer',
        'object_id' => 'integer',
        'date_begin' => 'datetime',
        'date_forecast' => 'datetime',
        'date_end' => 'datetime',
        'status_id' => 'integer',
        'adherent_id' => 'integer',
        'comments' => 'varchar(200)'
    ];
    private ?int $rent_id = null;
    private ?int $object_id = null;
    private string $date_begin;
    private ?string $date_forecast = null;
    private ?string $date_end = null;
    private ?int $status_id = null;
    private ?int $adherent_id = null;
    private string $comments = '';

    //from joined tables
    private string $status_text = '';
    private bool $in_stock = false;
    private string $nom_adh = '';
    private string $prenom_adh = '';

    /**
     * Default constructor
     *
     * @param Db                                 $zdb  Database instance
     * @param int|ArrayObject<string,mixed>|null $args Either an int with rent id, null, or a resultset row
     */
    public function __construct(private Db $zdb, int|ArrayObject|null $args = null)
    {
        $this->date_begin = date('Y-m-d H:i:s');

        if (is_int($args)) {
            $select = $this->zdb->select(LEND_PREFIX . self::TABLE)
                    ->where([self::PK => $args]);
            $result = $this->zdb->execute($select);
            if ($result->count() == 1) {
                $this->loadFromRS($result->current());
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string,mixed> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->rent_id = (int)$r['rent_id'];
        $this->object_id = (int)$r['object_id'];
        $this->date_begin = (string)$r['date_begin'];
        $this->date_forecast = $r['date_forecast'] !== null ? (string)$r['date_forecast'] : null;
        $this->date_end = $r['date_end'] !== null ? (string)$r['date_end'] : null;
        $this->status_id = $r['status_id'] !== null ? (int)$r['status_id'] : null;
        $this->adherent_id = $r['adherent_id'] !== null ? (int)$r['adherent_id'] : null;
        $this->comments = (string)$r['comments'];

        if (isset($r['status_text'])) {
            $this->status_text = (string)$r['status_text'];
        }
        if (isset($r['in_stock'])) {
            $this->in_stock = $r['in_stock'] == '1';
        }
        if (isset($r['nom_adh'])) {
            $this->nom_adh = (string)$r['nom_adh'];
        }
        if (isset($r['prenom_adh'])) {
            $this->prenom_adh = (string)$r['prenom_adh'];
        }
    }

    /**
     * Store current element
     */
    public function store(): void
    {
        $need_transaction = !$this->zdb->inTransaction();
        try {
            if ($need_transaction) {
                $this->zdb->beginTransaction();
            }
            $values = [];

            foreach (array_keys($this->fields) as $k) {
                $values[$k] = $this->$k;
            }

            if ($this->rent_id === null) {
                unset($values[self::PK]);
                $insert = $this->zdb->insert(LEND_PREFIX . self::TABLE)
                        ->values($values);
                $result = $this->zdb->execute($insert);
                if ($result->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        // @phpstan-ignore arguments.count (laminas does not respect its own interfaces)
                        $this->rent_id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . 'lend_rents_id_seq'
                        );
                    } else {
                        $this->rent_id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }
                    Analog::log(
                        'Rent #' . $this->rent_id . ' added.',
                        Analog::DEBUG
                    );
                } else {
                    throw new \Exception(_T("Rent has not been added", "objectslend"));
                }
            } else {
                $update = $this->zdb->update(LEND_PREFIX . self::TABLE)
                        ->set($values)
                        ->where([self::PK => $this->rent_id]);
                $this->zdb->execute($update);
            }
            if ($need_transaction) {
                $this->zdb->commit();
            }
        } catch (\Exception $e) {
            if ($need_transaction) {
                $this->zdb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Get ID
     */
    public function getId(): ?int
    {
        return $this->rent_id;
    }

    /**
     * Get object ID
     */
    public function getObjectId(): ?int
    {
        return $this->object_id;
    }

    /**
     * Set object ID
     *
     * @param int $object_id Object ID
     */
    public function setObjectId(int $object_id): self
    {
        $this->object_id = $object_id;
        return $this;
    }

    /**
     * Get status ID
     */
    public function getStatusId(): ?int
    {
        return $this->status_id;
    }

    /**
     * Set status ID
     *
     * @param int $status_id Status ID
     */
    public function setStatusId(int $status_id): self
    {
        $this->status_id = $status_id;
        return $this;
    }

    /**
     * Get member ID
     */
    public function getAdherentId(): ?int
    {
        return $this->adherent_id;
    }

    /**
     * Set member ID
     *
     * @param ?int $adherent_id Member ID, null or 0 (superadmin) for none
     */
    public function setAdherentId(?int $adherent_id): self
    {
        $this->adherent_id = $adherent_id > 0 ? $adherent_id : null;
        return $this;
    }

    /**
     * Get comments
     */
    public function getComments(): string
    {
        return $this->comments;
    }

    /**
     * Set comments, cut to the column size
     *
     * @param string $comments Comments
     */
    public function setComments(string $comments): self
    {
        $this->comments = mb_substr($comments, 0, 200);
        return $this;
    }

    /**
     * Get localized begin date and time
     */
    public function getDateBegin(): string
    {
        return $this->formatDate($this->date_begin, _T('Y-m-d H:i', 'objectslend'));
    }

    /**
     * Set begin date and time
     *
     * @param string $date Date and time, localized or not
     */
    public function setDateBegin(string $date): self
    {
        $this->date_begin = $this->parseDate($date, true) ?? $this->date_begin;
        return $this;
    }

    /**
     * Get localized expected return date
     */
    public function getDateForecast(): string
    {
        return $this->formatDate($this->date_forecast, _T('Y-m-d'));
    }

    /**
     * Set expected return date
     *
     * @param string $date Date, localized or not
     */
    public function setDateForecast(string $date): self
    {
        $this->date_forecast = $this->parseDate($date, false);
        return $this;
    }

    /**
     * Get localized end date and time
     */
    public function getDateEnd(): string
    {
        return $this->formatDate($this->date_end, _T('Y-m-d H:i', 'objectslend'));
    }

    /**
     * Set end date and time
     *
     * @param string $date Date and time, localized or not
     */
    public function setDateEnd(string $date): self
    {
        $this->date_end = $this->parseDate($date, true);
        return $this;
    }

    /**
     * Get status text, when loaded from a repository
     */
    public function getStatusText(): string
    {
        return $this->status_text;
    }

    /**
     * Is object in stock with this rent status, when loaded from a repository
     */
    public function isInStock(): bool
    {
        return $this->in_stock;
    }

    /**
     * Get member name, when loaded from a repository
     */
    public function getMemberName(): string
    {
        return trim($this->nom_adh . ' ' . $this->prenom_adh);
    }

    /**
     * Format a date
     *
     * @param ?string $date   Raw date
     * @param string  $format Output format
     */
    private function formatDate(?string $date, string $format): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        return (new \DateTime($date))->format($format);
    }

    /**
     * Parse a date, localized or not
     *
     * @param string $value    Date
     * @param bool   $datetime Whether value has a time
     *
     * @return ?string Raw date, null if it cannot be parsed
     */
    private function parseDate(string $value, bool $datetime): ?string
    {
        $fmt = 'Y-m-d';
        $tfmt = __('Y-m-d');
        if ($datetime) {
            $fmt .= ' H:i:s';
            $tfmt = __($fmt, 'objectslend');
        }

        $d = \DateTime::createFromFormat($tfmt, $value);
        if ($d === false) {
            //try with non localized date
            $d = \DateTime::createFromFormat($fmt, $value);
        }
        if ($d === false) {
            Analog::log(
                sprintf('Invalid date %1$s, required %2$s or %3$s', $value, $tfmt, $fmt),
                Analog::WARNING
            );
            return null;
        }
        return $d->format($fmt);
    }
}
