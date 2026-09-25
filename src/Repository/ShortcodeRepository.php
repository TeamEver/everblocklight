<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Repository;

use Everblocklight\Tools\Entity\Shortcode;

final class ShortcodeRepository extends AbstractEverblocklightRepository
{
    public function list(int $shopId, int $langId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT s.*, sl.title, sl.content
            FROM ' . $this->table('everblocklight_shortcode') . ' s
            LEFT JOIN ' . $this->table('everblocklight_shortcode_lang') . ' sl
                ON s.id_everblocklight_shortcode = sl.id_everblocklight_shortcode AND sl.id_lang = :langId
            WHERE s.id_shop = :shopId
            ORDER BY s.id_everblocklight_shortcode DESC',
            ['shopId' => $shopId, 'langId' => $langId]
        );
    }

    public function find(int $id, ?int $shopId = null, ?int $langId = null): ?Shortcode
    {
        $where = 'id_everblocklight_shortcode = :id';
        $params = ['id' => $id];
        if ($shopId !== null && $shopId > 0) {
            $where .= ' AND id_shop = :shopId';
            $params['shopId'] = $shopId;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $this->table('everblocklight_shortcode') . ' WHERE ' . $where,
            $params
        );
        if (!$row) {
            return null;
        }

        return Shortcode::fromDatabase(
            $row,
            $this->langRows('everblocklight_shortcode_lang', 'id_everblocklight_shortcode', $id),
            $langId
        );
    }

    public function findAll(int $shopId, int $langId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.*, sl.title, sl.content, sl.id_lang
            FROM ' . $this->table('everblocklight_shortcode') . ' s
            INNER JOIN ' . $this->table('everblocklight_shortcode_lang') . ' sl
                ON s.id_everblocklight_shortcode = sl.id_everblocklight_shortcode AND sl.id_lang = :langId
            WHERE s.id_shop = :shopId
            ORDER BY s.shortcode ASC',
            ['shopId' => $shopId, 'langId' => $langId]
        );

        return array_map(static fn (array $row): Shortcode => Shortcode::fromDatabase($row, [], $langId), $rows);
    }

    public function findIds(int $shopId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id_everblocklight_shortcode FROM ' . $this->table('everblocklight_shortcode') . ' WHERE id_shop = :shopId',
            ['shopId' => $shopId]
        );
    }

    public function findContentByShortcode(string $shortcode, int $shopId, int $langId): string
    {
        $content = $this->connection->fetchOne(
            'SELECT sl.content
            FROM ' . $this->table('everblocklight_shortcode') . ' s
            INNER JOIN ' . $this->table('everblocklight_shortcode_lang') . ' sl
                ON s.id_everblocklight_shortcode = sl.id_everblocklight_shortcode
            WHERE s.shortcode = :shortcode AND s.id_shop = :shopId AND sl.id_lang = :langId',
            ['shortcode' => $shortcode, 'shopId' => $shopId, 'langId' => $langId]
        );

        return is_string($content) ? $content : '';
    }

    public function save(Shortcode $shortcode, array $languages): int
    {
        $data = [
            'shortcode' => $shortcode->shortcode,
            'id_shop' => $shortcode->id_shop,
        ];
        $titles = is_array($shortcode->title) ? $shortcode->title : [];
        $contents = is_array($shortcode->content) ? $shortcode->content : [];

        $this->connection->beginTransaction();
        try {
            if ($shortcode->id) {
                $this->connection->update($this->databasePrefix . 'everblocklight_shortcode', $data, [
                    'id_everblocklight_shortcode' => $shortcode->id,
                    'id_shop' => $shortcode->id_shop,
                ]);
                $id = (int) $shortcode->id;
            } else {
                $this->connection->insert($this->databasePrefix . 'everblocklight_shortcode', $data);
                $id = (int) $this->connection->lastInsertId();
            }

            $this->upsertLangRows('everblocklight_shortcode_lang', 'id_everblocklight_shortcode', $id, $languages, [
                'title' => $titles,
                'content' => $contents,
            ]);

            $this->connection->commit();

            return $id;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id, int $shopId): bool
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->delete($this->databasePrefix . 'everblocklight_shortcode_lang', ['id_everblocklight_shortcode' => $id]);
            $deleted = $this->connection->delete($this->databasePrefix . 'everblocklight_shortcode', [
                'id_everblocklight_shortcode' => $id,
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
