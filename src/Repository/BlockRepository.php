<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Repository;

use Everblocklight\Tools\Entity\Block;

final class BlockRepository extends AbstractEverblocklightRepository
{
    public function list(int $shopId, int $langId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT b.*, bl.content, bl.custom_code, h.title AS hook_name
            FROM ' . $this->table('everblocklight') . ' b
            LEFT JOIN ' . $this->table('everblocklight_lang') . ' bl ON b.id_everblocklight = bl.id_everblocklight AND bl.id_lang = :langId
            LEFT JOIN ' . $this->table('hook') . ' h ON h.id_hook = b.id_hook
            WHERE b.id_shop = :shopId
            ORDER BY h.title ASC, b.position ASC, b.id_everblocklight ASC',
            ['shopId' => $shopId, 'langId' => $langId]
        );
    }

    public function find(int $id, ?int $shopId = null, ?int $langId = null): ?Block
    {
        $where = 'id_everblocklight = :id';
        $params = ['id' => $id];
        if ($shopId !== null && $shopId > 0) {
            $where .= ' AND id_shop = :shopId';
            $params['shopId'] = $shopId;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $this->table('everblocklight') . ' WHERE ' . $where,
            $params
        );
        if (!$row) {
            return null;
        }

        $langRows = $this->langRows('everblocklight_lang', 'id_everblocklight', $id);
        if ($langId !== null && $langId > 0) {
            $langRows = array_values(array_filter($langRows, static fn (array $row): bool => (int) $row['id_lang'] === $langId));
        }

        return Block::fromDatabase($row, $langRows);
    }

    public function findAllForShop(int $langId, int $shopId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT b.*,
                COALESCE(NULLIF(bl.content, \'\'), NULLIF(bld.content, \'\'), \'\') AS content,
                COALESCE(NULLIF(bl.custom_code, \'\'), NULLIF(bld.custom_code, \'\'), \'\') AS custom_code
            FROM ' . $this->table('everblocklight') . ' b
            LEFT JOIN ' . $this->table('everblocklight_lang') . ' bl
                ON b.id_everblocklight = bl.id_everblocklight AND bl.id_lang = :langId
            LEFT JOIN ' . $this->table('everblocklight_lang') . ' bld
                ON b.id_everblocklight = bld.id_everblocklight AND bld.id_lang = :langIdDefault
            WHERE b.id_shop = :shopId
            ORDER BY b.position ASC',
            [
                'langId' => $langId,
                'langIdDefault' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                'shopId' => $shopId,
            ]
        );
    }

    public function findActiveForHook(int $hookId, int $langId, int $shopId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT b.*,
                COALESCE(NULLIF(bl.content, \'\'), NULLIF(bld.content, \'\'), \'\') AS content,
                COALESCE(NULLIF(bl.custom_code, \'\'), NULLIF(bld.custom_code, \'\'), \'\') AS custom_code
            FROM ' . $this->table('everblocklight') . ' b
            LEFT JOIN ' . $this->table('everblocklight_lang') . ' bl
                ON b.id_everblocklight = bl.id_everblocklight AND bl.id_lang = :langId
            LEFT JOIN ' . $this->table('everblocklight_lang') . ' bld
                ON b.id_everblocklight = bld.id_everblocklight AND bld.id_lang = :langIdDefault
            WHERE b.id_hook = :hookId
              AND b.id_shop = :shopId
              AND b.active = 1
            ORDER BY b.position ASC',
            [
                'hookId' => $hookId,
                'langId' => $langId,
                'langIdDefault' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                'shopId' => $shopId,
            ]
        );
    }

    public function save(Block $block, array $languages): int
    {
        $data = [
            'name' => $block->name,
            'id_hook' => $block->id_hook,
            'only_home' => (int) $block->only_home,
            'only_category' => (int) $block->only_category,
            'only_category_product' => (int) $block->only_category_product,
            'only_manufacturer' => (int) $block->only_manufacturer,
            'only_supplier' => (int) $block->only_supplier,
            'only_cms_category' => (int) $block->only_cms_category,
            'obfuscate_link' => (int) $block->obfuscate_link,
            'add_container' => (int) $block->add_container,
            'lazyload' => (int) $block->lazyload,
            'device' => $block->device,
            'id_shop' => $block->id_shop,
            'position' => $block->position,
            'categories' => $block->categories,
            'manufacturers' => $block->manufacturers,
            'suppliers' => $block->suppliers,
            'cms_categories' => $block->cms_categories,
            'groups' => $block->groups,
            'background' => $block->background,
            'css_class' => $block->css_class,
            'data_attribute' => $block->data_attribute,
            'bootstrap_class' => $block->bootstrap_class,
            'modal' => (int) $block->modal,
            'delay' => $block->delay,
            'timeout' => $block->timeout,
            'date_start' => $this->normalizeNullableDate($block->date_start),
            'date_end' => $this->normalizeNullableDate($block->date_end),
            'active' => (int) $block->active,
        ];

        $this->connection->beginTransaction();
        try {
            if ($block->id) {
                $this->connection->update($this->databasePrefix . 'everblocklight', $data, [
                    'id_everblocklight' => $block->id,
                    'id_shop' => $block->id_shop,
                ]);
                $id = (int) $block->id;
            } else {
                $this->connection->insert($this->databasePrefix . 'everblocklight', $data);
                $id = (int) $this->connection->lastInsertId();
            }

            $this->upsertLangRows('everblocklight_lang', 'id_everblocklight', $id, $languages, [
                'content' => $block->content,
                'custom_code' => $block->custom_code,
            ]);

            $this->connection->commit();

            return $id;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function setActive(int $id, int $shopId, bool $active): bool
    {
        return $this->connection->update($this->databasePrefix . 'everblocklight', [
            'active' => $active ? 1 : 0,
        ], [
            'id_everblocklight' => $id,
            'id_shop' => $shopId,
        ]) > 0;
    }

    public function duplicate(int $id, int $shopId, array $languages): int
    {
        $block = $this->find($id, $shopId);
        if (!$block instanceof Block) {
            return 0;
        }

        $block->id = null;
        $block->id_everblocklight = null;
        $block->name = trim($block->name . ' (copy)');
        $block->position = $this->getNextPosition($shopId, (int) $block->id_hook);

        return $this->save($block, $languages);
    }

    public function getNextPosition(int $shopId, int $hookId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(position), 0) + 1
            FROM ' . $this->table('everblocklight') . '
            WHERE id_shop = :shopId AND id_hook = :hookId',
            ['shopId' => $shopId, 'hookId' => $hookId]
        );
    }

    public function delete(int $id, int $shopId): bool
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->delete($this->databasePrefix . 'everblocklight_lang', ['id_everblocklight' => $id]);
            $deleted = $this->connection->delete($this->databasePrefix . 'everblocklight', [
                'id_everblocklight' => $id,
                'id_shop' => $shopId,
            ]);
            $this->connection->commit();

            return $deleted > 0;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }
}
