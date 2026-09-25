<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Handler;

use Everblocklight\Tools\Entity\Block;
use Everblocklight\Tools\Entity\Shortcode;
use Everblocklight\Tools\Query\GetAdminItemQuery;
use Everblocklight\Tools\Repository\BlockRepository;
use Everblocklight\Tools\Repository\HookRepository;
use Everblocklight\Tools\Repository\ShortcodeRepository;

final class GetAdminItemHandler
{
    public function __construct(
        private BlockRepository $blockRepository,
        private ShortcodeRepository $shortcodeRepository,
        private HookRepository $hookRepository
    ) {
    }

    public function __invoke(GetAdminItemQuery $query): array
    {
        return $this->handle($query);
    }

    public function handle(GetAdminItemQuery $query): array
    {
        return match ($query->section) {
            'blocks' => $this->blockData($query),
            'shortcodes' => $this->shortcodeData($query),
            'hooks' => $this->hookData($query),
            default => [],
        };
    }

    private function blockData(GetAdminItemQuery $query): array
    {
        $block = $query->id ? $this->blockRepository->find($query->id, $query->shopId) : new Block();
        if (!$block instanceof Block) {
            return [];
        }

        $data = get_object_vars($block);
        foreach (['categories', 'manufacturers', 'suppliers', 'cms_categories', 'groups'] as $field) {
            $data[$field] = $this->decodeIdList($data[$field] ?? null);
        }
        if (!$query->id) {
            $data['active'] = true;
            $data['add_container'] = true;
        }
        $this->normalizeBooleanFields($data, [
            'only_home',
            'only_category',
            'only_category_product',
            'only_manufacturer',
            'only_supplier',
            'only_cms_category',
            'obfuscate_link',
            'add_container',
            'lazyload',
            'modal',
            'active',
        ]);
        $this->flattenLocalized($data, 'content', $block->content);
        $this->flattenLocalized($data, 'custom_code', $block->custom_code);

        return $data;
    }

    private function shortcodeData(GetAdminItemQuery $query): array
    {
        $shortcode = $query->id ? $this->shortcodeRepository->find($query->id, $query->shopId) : new Shortcode();
        if (!$shortcode instanceof Shortcode) {
            return [];
        }

        $data = get_object_vars($shortcode);
        $this->flattenLocalized($data, 'title', is_array($shortcode->title) ? $shortcode->title : []);
        $this->flattenLocalized($data, 'content', is_array($shortcode->content) ? $shortcode->content : []);

        return $data;
    }

    private function hookData(GetAdminItemQuery $query): array
    {
        if (!$query->id) {
            return ['active' => true];
        }

        $data = $this->hookRepository->find($query->id);
        if (!is_array($data)) {
            return [];
        }

        $this->normalizeBooleanFields($data, ['active']);

        return $data;
    }

    private function flattenLocalized(array &$data, string $field, array $values): void
    {
        foreach ($values as $langId => $value) {
            $data[$field . '_' . (int) $langId] = $value;
        }
    }

    private function normalizeBooleanFields(array &$data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->normalizeBoolean($data[$field]);
        }
    }

    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['', '0', 'false', 'off', 'no'], true)) {
                return false;
            }
            if (in_array($value, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
        }

        return (bool) $value;
    }

    private function decodeIdList($value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map('intval', $decoded));
    }
}
