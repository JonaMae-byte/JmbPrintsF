<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426105000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_at and created_by columns to product table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD created_by VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP created_at, DROP created_by');
    }
}
