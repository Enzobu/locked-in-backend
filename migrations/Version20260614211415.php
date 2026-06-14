<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260614211415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cancellation/refund tracking columns to reservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD cancelled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD refund_id VARCHAR(255) DEFAULT NULL, ADD refund_status VARCHAR(50) DEFAULT \'none\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP cancelled_at, DROP refund_id, DROP refund_status');
    }
}
