<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Handler;

use Everblocklight\Tools\Command\DeleteAdminItemCommand;
use Everblocklight\Tools\Entity\Block;
use Everblocklight\Tools\Repository\BlockRepository;
use Everblocklight\Tools\Repository\HookRepository;
use Everblocklight\Tools\Repository\ShortcodeRepository;
use Everblocklight\Tools\Service\EverblocklightCache;

final class DeleteAdminItemHandler
{
    public function __construct(
        private BlockRepository $blockRepository,
        private ShortcodeRepository $shortcodeRepository,
        private HookRepository $hookRepository
    ) {
    }

    public function __invoke(DeleteAdminItemCommand $command): bool
    {
        return $this->handle($command);
    }

    public function handle(DeleteAdminItemCommand $command): bool
    {
        $previous = match ($command->section) {
            'blocks' => $this->blockRepository->find($command->id, $command->shopId),
            'shortcodes' => $this->shortcodeRepository->find($command->id, $command->shopId),
            default => null,
        };
        $deleted = match ($command->section) {
            'blocks' => $this->blockRepository->delete($command->id, $command->shopId),
            'shortcodes' => $this->shortcodeRepository->delete($command->id, $command->shopId),
            'hooks' => $this->hookRepository->delete($command->id),
            default => false,
        };

        if ($deleted) {
            $this->clearObjectCache($command, $previous);
        }

        return $deleted;
    }

    private function clearObjectCache(DeleteAdminItemCommand $command, $previous): void
    {
        $languages = \Language::getLanguages(false);
        if ($command->section === 'blocks') {
            $hookId = $previous && isset($previous->id_hook) ? (int) $previous->id_hook : 0;
            Block::clearCache($command->id, $command->shopId, $languages, $hookId > 0 ? [$hookId] : []);

            return;
        }

        if ($command->section === 'shortcodes') {
            $shortcode = $previous && isset($previous->shortcode) ? trim((string) $previous->shortcode) : '';
            foreach ($languages as $language) {
                $langId = (int) ($language['id_lang'] ?? 0);
                if ($langId <= 0) {
                    continue;
                }
                EverblocklightCache::cacheDrop('EverblocklightShortcode_getAllShortcodes_' . $command->shopId . '_' . $langId);
                if ($shortcode !== '') {
                    EverblocklightCache::cacheDrop('EverblocklightShortcode_getEverShortcode_' . $shortcode . '_' . $command->shopId . '_' . $langId);
                }
            }
            EverblocklightCache::cacheDrop('EverblocklightShortcode_getAllShortcodeIds_' . $command->shopId);

            return;
        }
    }
}
