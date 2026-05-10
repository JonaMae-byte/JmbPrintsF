<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330103200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category_name snapshot to order_item.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item ADD category_name VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP category_name');
    }
}

