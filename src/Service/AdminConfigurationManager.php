<?php

declare(strict_types=1);

namespace Everblock\Tools\Service;

use Configuration;
use Context;
use Language;
use Store;
use Tools;

final class AdminConfigurationManager
{
    public function getFormData(\Everblock $module): array
    {
        $data = $module->getAdminConfigurationLegacyFormValues();
        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            $langId = (int) $language['id_lang'];
            $data['EVEROPTIONS_TITLE_' . $langId] = $data['EVEROPTIONS_TITLE'][$langId] ?? '';
        }
        unset($data['EVEROPTIONS_TITLE']);

        foreach ([
            'EVERBLOCK_LOAD_FRONT_CSS',
            'EVERBLOCK_USE_OBF',
            'EVERBLOCK_TINYMCE',
            'EVERINSTA_SHOW_CAPTION',
            'EVERBLOCK_GOOGLE_REVIEWS_SHOW_RATING',
            'EVERBLOCK_GOOGLE_REVIEWS_SHOW_AVATAR',
            'EVERBLOCK_GOOGLE_REVIEWS_SHOW_CTA',
            'EVERBLOCK_STORELOCATOR_TOGGLE',
        ] as $booleanField) {
            $defaultValue = $booleanField === 'EVERBLOCK_LOAD_FRONT_CSS' ? 1 : 0;
            $data[$booleanField] = (int) ($data[$booleanField] ?? $defaultValue);
        }

        return $data;
    }

    public function getViewContext(\Everblock $module): array
    {
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $stores = Store::getStores($idLang);
        $holidays = EverblockTools::getFrenchHolidays((int) date('Y'));

        $imageBaseUrl = $context->link->getBaseLink(null, null) . 'modules/' . $module->name . '/views/img/';
        $wordpressBackground = Configuration::get('EVERWP_POSTS_BG_IMAGE');
        $markerIcon = Configuration::get('EVERBLOCK_MARKER_ICON');

        $cronLinks = [];
        $cronToken = $module->getAdminConfigurationCronToken();
        foreach ($module->getAdminConfigurationAllowedActions() as $action) {
            $cronLinks[$action] = $context->link->getModuleLink(
                $module->name,
                'cron',
                [
                    'action' => $action,
                    'evertoken' => $cronToken,
                ]
            );
        }

        return [
            'cron_links' => $cronLinks,
            'current_images' => [
                'EVERWP_POSTS_BG_IMAGE' => $wordpressBackground ? $imageBaseUrl . $wordpressBackground : null,
                'EVERBLOCK_MARKER_ICON' => $markerIcon ? $imageBaseUrl . $markerIcon : null,
            ],
            'has_instagram_token' => (bool) Configuration::get('EVERINSTA_ACCESS_TOKEN'),
            'has_stores' => !empty($stores),
            'holidays' => $holidays,
            'languages' => Language::getLanguages(false),
            'module_version' => $module->version,
            'stats' => $module->getAdminConfigurationModuleStatistics(),
            'stores' => $stores,
        ];
    }

    public function processRequest(\Everblock $module): array
    {
        $module->prepareAdminConfigurationEnvironment();
        $module->resetAdminConfigurationMessages();
        $errors = [];
        $success = [];

        if (Tools::isSubmit('deleteEVERBLOCK_MARKER_ICON')) {
            $icon = Configuration::get('EVERBLOCK_MARKER_ICON');
            if ($icon) {
                $path = _PS_MODULE_DIR_ . $module->name . '/views/img/' . $icon;
                if (file_exists($path)) {
                    @unlink($path);
                }
                Configuration::deleteByName('EVERBLOCK_MARKER_ICON');
                $success[] = $module->l('Marker icon removed.');
            }
        }

        if (Tools::isSubmit('deleteEVERWP_POSTS_BG_IMAGE')) {
            $background = Configuration::get('EVERWP_POSTS_BG_IMAGE');
            if ($background) {
                $path = _PS_MODULE_DIR_ . $module->name . '/views/img/' . $background;
                if (file_exists($path)) {
                    @unlink($path);
                }
                Configuration::deleteByName('EVERWP_POSTS_BG_IMAGE');
                $success[] = $module->l('WordPress background image removed.');
            }
        }

        if (Tools::isSubmit('submit' . $module->name . 'Module')) {
            $module->runAdminConfigurationPostValidation();
            if (!count($module->getAdminConfigurationMessages()['errors'])) {
                $module->runAdminConfigurationPostProcess();
            }
        }

        if (Tools::isSubmit('submitEmptyCache')) {
            $module->runAdminConfigurationCacheCleanup();
        }

        $moduleMessages = $module->getAdminConfigurationMessages();

        return [
            'errors' => array_merge($moduleMessages['errors'], $errors),
            'success' => array_merge($moduleMessages['success'], $success),
        ];
    }
}
