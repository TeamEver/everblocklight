<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Service;

use Configuration;
use Context;
use Language;
use Store;
use Tools;

final class AdminConfigurationManager
{
    public function getFormData(\Everblocklight $module): array
    {
        $data = $module->getAdminConfigurationLegacyFormValues();
        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            $langId = (int) $language['id_lang'];
            $data['EVERBLOCKLIGHT_OPTIONS_TITLE_' . $langId] = $data['EVERBLOCKLIGHT_OPTIONS_TITLE'][$langId] ?? '';
        }
        unset($data['EVERBLOCKLIGHT_OPTIONS_TITLE']);

        foreach ([
            'EVERBLOCKLIGHT_LOAD_FRONT_CSS',
            'EVERBLOCKLIGHT_USE_OBF',
            'EVERBLOCKLIGHT_TINYMCE',
            'EVERBLOCKLIGHT_INSTA_SHOW_CAPTION',
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING',
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR',
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA',
            'EVERBLOCKLIGHT_STORELOCATOR_TOGGLE',
        ] as $booleanField) {
            $defaultValue = $booleanField === 'EVERBLOCKLIGHT_LOAD_FRONT_CSS' ? 1 : 0;
            $data[$booleanField] = (int) ($data[$booleanField] ?? $defaultValue);
        }

        return $data;
    }

    public function getViewContext(\Everblocklight $module): array
    {
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $stores = Store::getStores($idLang);
        $holidays = EverblocklightTools::getFrenchHolidays((int) date('Y'));

        $imageBaseUrl = $context->link->getBaseLink(null, null) . 'modules/' . $module->name . '/views/img/';
        $wordpressBackground = Configuration::get('EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE');
        $markerIcon = Configuration::get('EVERBLOCKLIGHT_MARKER_ICON');

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
                'EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE' => $wordpressBackground ? $imageBaseUrl . $wordpressBackground : null,
                'EVERBLOCKLIGHT_MARKER_ICON' => $markerIcon ? $imageBaseUrl . $markerIcon : null,
            ],
            'has_instagram_token' => (bool) Configuration::get('EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN'),
            'has_stores' => !empty($stores),
            'holidays' => $holidays,
            'languages' => Language::getLanguages(false),
            'module_version' => $module->version,
            'stats' => $module->getAdminConfigurationModuleStatistics(),
            'stores' => $stores,
        ];
    }

    public function processRequest(\Everblocklight $module): array
    {
        $module->prepareAdminConfigurationEnvironment();
        $module->resetAdminConfigurationMessages();
        $errors = [];
        $success = [];

        if (Tools::isSubmit('deleteEVERBLOCKLIGHT_MARKER_ICON')) {
            $icon = Configuration::get('EVERBLOCKLIGHT_MARKER_ICON');
            if ($icon) {
                $path = _PS_MODULE_DIR_ . $module->name . '/views/img/' . $icon;
                if (file_exists($path)) {
                    @unlink($path);
                }
                Configuration::deleteByName('EVERBLOCKLIGHT_MARKER_ICON');
                $success[] = $module->l('Marker icon removed.');
            }
        }

        if (Tools::isSubmit('deleteEVERBLOCKLIGHT_WP_POSTS_BG_IMAGE')) {
            $background = Configuration::get('EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE');
            if ($background) {
                $path = _PS_MODULE_DIR_ . $module->name . '/views/img/' . $background;
                if (file_exists($path)) {
                    @unlink($path);
                }
                Configuration::deleteByName('EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE');
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
