<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Entity;

use Doctrine\ORM\Mapping as ORM;
use Everblocklight\Tools\Repository\RepositoryProvider;
use Everblocklight\Tools\Repository\ShortcodeRepository;
use Everblocklight\Tools\Service\EverblocklightCache;
use Language;

/**
 * @ORM\Table(name="everblocklight_shortcode")
 * @ORM\Entity(repositoryClass="Everblocklight\Tools\Repository\ShortcodeRepository")
 */
class Shortcode
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id_everblocklight_shortcode", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public ?int $id = null;
    public ?int $id_everblocklight_shortcode = null;

    /** @ORM\Column(name="shortcode", type="text", nullable=true) */
    public string $shortcode = '';
    /** @ORM\Column(name="id_shop", type="integer") */
    public int $id_shop = 1;

    /** @var array<int, string>|string */
    public $title = [];
    /** @var array<int, string>|string */
    public $content = [];

    public function __construct(?int $id = null, ?int $idLang = null, ?int $idShop = null)
    {
        if ($id !== null && $id > 0) {
            $loaded = self::repository()->find($id, $idShop, $idLang);
            if ($loaded instanceof self) {
                foreach (get_object_vars($loaded) as $property => $value) {
                    $this->{$property} = $value;
                }
            }
        }
    }

    public static function repository(): ShortcodeRepository
    {
        /** @var ShortcodeRepository $repository */
        $repository = RepositoryProvider::get('everblocklight.repository.shortcode');

        return $repository;
    }

    public static function fromDatabase(array $row, array $langRows = [], ?int $singleLangId = null): self
    {
        $shortcode = new self();
        $shortcode->id = isset($row['id_everblocklight_shortcode']) ? (int) $row['id_everblocklight_shortcode'] : null;
        $shortcode->id_everblocklight_shortcode = $shortcode->id;
        $shortcode->shortcode = (string) ($row['shortcode'] ?? '');
        $shortcode->id_shop = (int) ($row['id_shop'] ?? 1);

        if (isset($row['id_lang'])) {
            $langRows[] = $row;
        }

        $titles = [];
        $contents = [];
        foreach ($langRows as $langRow) {
            $langId = (int) ($langRow['id_lang'] ?? 0);
            if ($langId <= 0) {
                continue;
            }
            $titles[$langId] = (string) ($langRow['title'] ?? '');
            $contents[$langId] = (string) ($langRow['content'] ?? '');
        }

        if ($singleLangId !== null && $singleLangId > 0) {
            $shortcode->title = $titles[$singleLangId] ?? '';
            $shortcode->content = $contents[$singleLangId] ?? '';
        } else {
            $shortcode->title = $titles;
            $shortcode->content = $contents;
        }

        return $shortcode;
    }

    public function save(): bool
    {
        $this->id = self::repository()->save($this, Language::getLanguages(false));
        $this->id_everblocklight_shortcode = $this->id;

        return $this->id > 0;
    }

    public function delete(): bool
    {
        if (!$this->id) {
            return true;
        }

        return self::repository()->delete($this->id, $this->id_shop);
    }

    public static function getAllShortcodes(int $idShop, int $langId): array
    {
        $cacheId = 'EverblocklightShortcode_getAllShortcodes_' . $idShop . '_' . $langId;
        if (!EverblocklightCache::isCacheStored($cacheId)) {
            $shortcodes = array_map(
                static fn (array $row): self => self::fromDatabase($row, [], $langId),
                self::findAllLegacy($idShop, $langId)
            );
            EverblocklightCache::cacheStore($cacheId, $shortcodes);

            return $shortcodes;
        }

        return (array) EverblocklightCache::cacheRetrieve($cacheId);
    }

    public static function getAllShortcodeIds(int $idShop): array
    {
        $cacheId = 'EverblocklightShortcode_getAllShortcodeIds_' . $idShop;
        if (!EverblocklightCache::isCacheStored($cacheId)) {
            $ids = (array) \Db::getInstance()->executeS(
                'SELECT id_everblocklight_shortcode
                FROM `' . _DB_PREFIX_ . 'everblocklight_shortcode`
                WHERE id_shop = ' . (int) $idShop
            );
            EverblocklightCache::cacheStore($cacheId, $ids);

            return $ids;
        }

        return (array) EverblocklightCache::cacheRetrieve($cacheId);
    }

    public static function getEverShortcode(string $shortcode, int $shopId, int $langId): string
    {
        $cacheId = 'EverblocklightShortcode_getEverShortcode_' . trim($shortcode) . '_' . $shopId . '_' . $langId;
        if (!EverblocklightCache::isCacheStored($cacheId)) {
            $content = (string) \Db::getInstance()->getValue(
                'SELECT sl.content
                FROM `' . _DB_PREFIX_ . 'everblocklight_shortcode` s
                INNER JOIN `' . _DB_PREFIX_ . 'everblocklight_shortcode_lang` sl
                    ON s.id_everblocklight_shortcode = sl.id_everblocklight_shortcode
                WHERE s.shortcode = "' . pSQL($shortcode) . '"
                  AND s.id_shop = ' . (int) $shopId . '
                  AND sl.id_lang = ' . (int) $langId
            );
            EverblocklightCache::cacheStore($cacheId, $content);

            return $content;
        }

        return (string) EverblocklightCache::cacheRetrieve($cacheId);
    }

    private static function findAllLegacy(int $idShop, int $langId): array
    {
        return (array) \Db::getInstance()->executeS(
            'SELECT s.*, sl.title, sl.content, sl.id_lang
            FROM `' . _DB_PREFIX_ . 'everblocklight_shortcode` s
            INNER JOIN `' . _DB_PREFIX_ . 'everblocklight_shortcode_lang` sl
                ON s.id_everblocklight_shortcode = sl.id_everblocklight_shortcode
               AND sl.id_lang = ' . (int) $langId . '
            WHERE s.id_shop = ' . (int) $idShop . '
            ORDER BY s.shortcode ASC'
        );
    }
}
