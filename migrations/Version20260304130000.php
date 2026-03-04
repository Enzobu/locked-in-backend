<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add overtime surcharge percent to locker bays';
    }

    public function up(Schema $schema): void
    {
        $lockerBayTable = $schema->getTable('locker_bay');

        if (!$lockerBayTable->hasColumn('overtime_surcharge_percent')) {
            $lockerBayTable->addColumn('overtime_surcharge_percent', 'integer', ['default' => 0]);
        }
    }

    public function down(Schema $schema): void
    {
        $lockerBayTable = $schema->getTable('locker_bay');

        if ($lockerBayTable->hasColumn('overtime_surcharge_percent')) {
            $lockerBayTable->dropColumn('overtime_surcharge_percent');
        }
    }
}
