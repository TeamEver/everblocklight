<?php

declare(strict_types=1);

namespace Everblock\Tools\Handler;

use Everblock\Tools\Command\SaveAdminItemCommand;
use Everblock\Tools\Entity\Block;
use Everblock\Tools\Entity\Shortcode;
use Everblock\Tools\Repository\BlockRepository;
use Everblock\Tools\Repository\HookRepository;
use Everblock\Tools\Repository\ShortcodeRepository;
use Everblock\Tools\Service\EverblockCache;
use Everblock\Tools\Service\EverblockTools;

final class SaveAdminItemHandler
{
    public function __construct(
        private BlockRepository $blockRepository,
        private ShortcodeRepository $shortcodeRepository,
        private HookRepository $hookRepository
    ) {
    }

    public function __invoke(SaveAdminItemCommand $command): int
    {
        return $this->handle($command);
    }

    public function handle(SaveAdminItemCommand $command): int
    {
        $previous = $command->id ? $this->previousItem($command) : null;
        $id = match ($command->section) {
            'blocks' => $this->saveBlock($command),
            'shortcodes' => $this->saveShortcode($command),
            'hooks' => $this->hookRepository->save($command->id, $command->data),
            default => 0,
        };

        $this->clearObjectCache($command, $id, $previous);

        return $id;
    }

    private function saveBlock(SaveAdminItemCommand $command): int
    {
        $block = $command->id ? $this->blockRepository->find($command->id, $command->shopId) : new Block();
        $block ??= new Block();
        $data = $command->data;
        $block->id = $command->id;
        $block->id_everblock = $command->id;
        $block->id_shop = $command->shopId;
        $block->name = (string) ($data['name'] ?? '');
        $block->id_hook = (int) ($data['id_hook'] ?? 0);
        foreach (['only_home', 'only_category', 'only_category_product', 'only_manufacturer', 'only_supplier', 'only_cms_category', 'obfuscate_link', 'add_container', 'lazyload', 'modal', 'active'] as $boolField) {
            $block->{$boolField} = !empty($data[$boolField]);
        }
        foreach (['device', 'position', 'delay', 'timeout'] as $intField) {
            $block->{$intField} = (int) ($data[$intField] ?? 0);
        }
        foreach (['categories', 'manufacturers', 'suppliers', 'cms_categories', 'groups'] as $field) {
            $block->{$field} = json_encode(array_values(array_map('intval', (array) ($data[$field] ?? []))));
        }
        foreach (['background', 'css_class', 'data_attribute', 'bootstrap_class', 'date_start', 'date_end'] as $field) {
            $value = $data[$field] ?? null;
            $block->{$field} = $value !== null && $value !== '' ? (string) $value : null;
        }
        $block->content = $this->localized($data, 'content', $command->languages, true);
        $block->custom_code = $this->localized($data, 'custom_code', $command->languages, true);

        $id = $this->blockRepository->save($block, $command->languages);
        $this->registerModuleInHook((int) $block->id_hook);

        return $id;
    }

    private function saveShortcode(SaveAdminItemCommand $command): int
    {
        $shortcode = $command->id ? $this->shortcodeRepository->find($command->id, $command->shopId) : new Shortcode();
        $shortcode ??= new Shortcode();
        $shortcode->id = $command->id;
        $shortcode->id_everblock_shortcode = $command->id;
        $shortcode->id_shop = $command->shopId;
        $shortcode->shortcode = (string) ($command->data['shortcode'] ?? '');
        $shortcode->title = $this->localized($command->data, 'title', $command->languages);
        $shortcode->content = $this->localized($command->data, 'content', $command->languages, true);

        return $this->shortcodeRepository->save($shortcode, $command->languages);
    }

    private function localized(array $data, string $field, array $languages, bool $convertImages = false): array
    {
        $values = [];
        foreach ($languages as $language) {
            $langId = (int) ($language['id_lang'] ?? $language['id'] ?? 0);
            if ($langId > 0) {
                $value = (string) ($data[$field . '_' . $langId] ?? '');
                $values[$langId] = $convertImages ? EverblockTools::convertImagesToWebP($value) : $value;
            }
        }

        return $values;
    }

    private function previousItem(SaveAdminItemCommand $command)
    {
        return match ($command->section) {
            'blocks' => $this->blockRepository->find((int) $command->id, $command->shopId),
            'shortcodes' => $this->shortcodeRepository->find((int) $command->id, $command->shopId),
            default => null,
        };
    }

    private function clearObjectCache(SaveAdminItemCommand $command, int $id, $previous): void
    {
        if ($command->section === 'blocks') {
            $hookIds = [];
            if ($previous instanceof Block && $previous->id_hook > 0) {
                $hookIds[] = (int) $previous->id_hook;
            }
            if (!empty($command->data['id_hook'])) {
                $hookIds[] = (int) $command->data['id_hook'];
            }

            Block::clearCache($id, $command->shopId, $command->languages, $hookIds);

            return;
        }

        if ($command->section === 'shortcodes') {
            $shortcodes = [];
            if ($previous instanceof Shortcode) {
                $shortcodes[] = trim((string) $previous->shortcode);
            }
            $shortcodes[] = trim((string) ($command->data['shortcode'] ?? ''));
            foreach ($command->languages as $language) {
                $langId = (int) ($language['id_lang'] ?? $language['id'] ?? 0);
                if ($langId <= 0) {
                    continue;
                }
                EverblockCache::cacheDrop('EverblockShortcode_getAllShortcodes_' . $command->shopId . '_' . $langId);
                foreach (array_unique(array_filter($shortcodes)) as $shortcode) {
                    EverblockCache::cacheDrop('EverblockShortcode_getEverShortcode_' . $shortcode . '_' . $command->shopId . '_' . $langId);
                }
            }
            EverblockCache::cacheDrop('EverblockShortcode_getAllShortcodeIds_' . $command->shopId);

            return;
        }

    }

    private function registerModuleInHook(int $hookId): void
    {
        if ($hookId <= 0) {
            return;
        }

        $hookName = \Hook::getNameById($hookId);
        if (!$hookName || !\Validate::isHookName($hookName)) {
            return;
        }

        $module = \Module::getInstanceByName('everblock');
        if (!$module instanceof \Module || $module->isRegisteredInHook($hookName)) {
            return;
        }

        $module->registerHook($hookName);
    }
}
