<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Repository;

use Galette\Core\Db;
use Galette\Entity\Adherent;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use Laminas\Db\Sql\Select;

/**
 * Rents repository
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Rents
{
    /**
     * Constructor
     *
     * @param Db $zdb Database instance
     */
    public function __construct(private Db $zdb)
    {
    }

    /**
     * Get rents history of an object, most recent first
     *
     * @param int  $object_id Object ID
     * @param bool $only_last Only retrieve last rent
     *
     * @return LendRent[]
     */
    public function getForObject(int $object_id, bool $only_last = false): array
    {
        $select = $this->zdb->select(LEND_PREFIX . LendRent::TABLE, 'r')
            ->join(
                ['a' => PREFIX_DB . Adherent::TABLE],
                'a.' . Adherent::PK . ' = r.adherent_id',
                ['prenom_adh', 'nom_adh'],
                Select::JOIN_LEFT
            )
            ->join(
                ['s' => PREFIX_DB . LEND_PREFIX . LendStatus::TABLE],
                's.' . LendStatus::PK . ' = r.' . LendStatus::PK,
                ['status_text', 'in_stock']
            )
            ->where(['r.object_id' => $object_id])
            ->order('r.date_begin desc');

        if ($only_last === true) {
            $select->offset(0)->limit(1);
        }

        $rents = [];
        foreach ($this->zdb->execute($select) as $row) {
            $rents[] = new LendRent($this->zdb, $row);
        }
        return $rents;
    }

    /**
     * Close all open rents of an object
     *
     * @param int    $object_id Object ID
     * @param string $comments  Comment that replaces the one of closed rents
     */
    public function closeAllForObject(int $object_id, string $comments): void
    {
        $select = $this->zdb->select(LEND_PREFIX . LendRent::TABLE)
            ->where(
                [
                    'object_id' => $object_id,
                    'date_end' => null
                ]
            );

        foreach ($this->zdb->execute($select) as $row) {
            $rent = new LendRent($this->zdb, $row);
            $rent
                ->setDateEnd(date('Y-m-d H:i:s'))
                ->setComments($comments); //FIXME: will replace any existing comments :/
            $rent->store();
        }
    }
}
