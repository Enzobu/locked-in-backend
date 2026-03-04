<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe and overtime billing fields for reservations and customers';
    }

    public function up(Schema $schema): void
    {
        $customerTable = $schema->getTable('customer');
        if (!$customerTable->hasColumn('stripe_customer_id')) {
            $customerTable->addColumn('stripe_customer_id', 'string', ['length' => 255, 'notnull' => false]);
        }

        $reservationTable = $schema->getTable('reservation');

        if (!$reservationTable->hasColumn('planned_amount_cents')) {
            $reservationTable->addColumn('planned_amount_cents', 'integer', ['default' => 0]);
        }

        if (!$reservationTable->hasColumn('currency')) {
            $reservationTable->addColumn('currency', 'string', ['length' => 3, 'default' => 'eur']);
        }

        if (!$reservationTable->hasColumn('payment_intent_id')) {
            $reservationTable->addColumn('payment_intent_id', 'string', ['length' => 255, 'notnull' => false]);
        }

        if (!$reservationTable->hasColumn('payment_status')) {
            $reservationTable->addColumn('payment_status', 'string', ['length' => 50, 'default' => 'unpaid']);
        }

        if (!$reservationTable->hasColumn('actual_ends_at')) {
            $reservationTable->addColumn('actual_ends_at', 'datetime_immutable', ['notnull' => false]);
        }

        if (!$reservationTable->hasColumn('overtime_minutes')) {
            $reservationTable->addColumn('overtime_minutes', 'integer', ['default' => 0]);
        }

        if (!$reservationTable->hasColumn('overtime_amount_cents')) {
            $reservationTable->addColumn('overtime_amount_cents', 'integer', ['default' => 0]);
        }

        if (!$reservationTable->hasColumn('overtime_payment_intent_id')) {
            $reservationTable->addColumn('overtime_payment_intent_id', 'string', ['length' => 255, 'notnull' => false]);
        }

        if (!$reservationTable->hasColumn('overtime_payment_status')) {
            $reservationTable->addColumn('overtime_payment_status', 'string', ['length' => 50, 'default' => 'none']);
        }
    }

    public function down(Schema $schema): void
    {
        $customerTable = $schema->getTable('customer');
        if ($customerTable->hasColumn('stripe_customer_id')) {
            $customerTable->dropColumn('stripe_customer_id');
        }

        $reservationTable = $schema->getTable('reservation');

        foreach ([
            'planned_amount_cents',
            'currency',
            'payment_intent_id',
            'payment_status',
            'actual_ends_at',
            'overtime_minutes',
            'overtime_amount_cents',
            'overtime_payment_intent_id',
            'overtime_payment_status',
        ] as $column) {
            if ($reservationTable->hasColumn($column)) {
                $reservationTable->dropColumn($column);
            }
        }
    }
}
