<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enforce the unique constraints that were previously declared via the (now
 * no-op) Table(uniqueConstraints:) attribute and therefore never created at the
 * database level: company SIRET/SIREN, locker (bay, number) + hardware id,
 * specification name and (company, bay name).
 */
final class Version20260614220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the unique indexes that were silently dropped by the deprecated Table attribute';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_company_siret ON company (siret)');
        $this->addSql('CREATE UNIQUE INDEX uniq_company_siren ON company (siren)');
        $this->addSql('CREATE UNIQUE INDEX uniq_company_locker_bay_name ON locker_bay (company_id, name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_locker_bay_number ON locker (locker_bay_id, number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_locker_hardware_id ON locker (hardware_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_specification_name ON specification (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_company_siret ON company');
        $this->addSql('DROP INDEX uniq_company_siren ON company');
        $this->addSql('DROP INDEX uniq_company_locker_bay_name ON locker_bay');
        $this->addSql('DROP INDEX uniq_locker_bay_number ON locker');
        $this->addSql('DROP INDEX uniq_locker_hardware_id ON locker');
        $this->addSql('DROP INDEX uniq_specification_name ON specification');
    }
}
