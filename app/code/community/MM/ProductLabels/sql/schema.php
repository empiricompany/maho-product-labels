<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('productlabels_rule')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('code', Types::STRING, length: 64))
            ->addColumn(Schema::column('name', Types::STRING, length: 128))
            ->addColumn(Schema::column('description', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('status', Types::SMALLINT, unsigned: true, default: 1))
            ->addColumn(Schema::column('conditions_serialized', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('attributes_required', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('position', Types::STRING, length: 20, default: 'top-left'))
            ->addColumn(Schema::column('priority', Types::INTEGER, default: 0))
            ->addColumn(Schema::column('stop', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('text_color', Types::STRING, length: 7, notNull: false))
            ->addColumn(Schema::column('bg_color', Types::STRING, length: 7, notNull: false))
            ->addColumn(Schema::column('from_date', Types::DATE_IMMUTABLE, notNull: false))
            ->addColumn(Schema::column('to_date', Types::DATE_IMMUTABLE, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_IMMUTABLE))
            ->addColumn(Schema::column('updated_at', Types::DATETIME_IMMUTABLE))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id')->create())
            ->addIndex(Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames('code'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status', 'priority'))
            ->setComment('Product label rules')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('productlabels_rule_text')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('text', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('image', Types::STRING, length: 255, notNull: false))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'store_id')->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('productlabels_rule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Product label text and image per store view')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('productlabels_rule_website')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('rule_id', 'website_id')->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('productlabels_rule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Product label to website associations')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('productlabels_rule_customer_group')
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('rule_id', 'customer_group_id')->create(),
            )
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('productlabels_rule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Product label customer group restriction')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('productlabels_index')
            ->addColumn(Schema::column('index_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('rule_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('product_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('website_id', Types::SMALLINT, unsigned: true))
            ->addColumn(Schema::column('customer_group_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('position', Types::STRING, length: 20, default: 'top-left'))
            ->addColumn(Schema::column('priority', Types::INTEGER, default: 0))
            ->addPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_id')->create(),
            )
            ->addIndex(
                Index::editor()->setType(IndexType::UNIQUE)->setUnquotedColumnNames(
                    'website_id',
                    'customer_group_id',
                    'product_id',
                    'rule_id',
                ),
            )
            ->addIndex(
                Index::editor()->setUnquotedColumnNames('product_id', 'website_id', 'customer_group_id'),
            )
            ->addIndex(Index::editor()->setUnquotedColumnNames('rule_id'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('rule_id')
                    ->setUnquotedReferencedTableName('productlabels_rule')
                    ->setUnquotedReferencedColumnNames('rule_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Product label index: one row per rule, website and customer group')
            ->create(),
    );
};
