<?php

/**
 * 2019-2023 Team Ever
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *  @author    Team Ever <https://www.team-ever.com/>
 *  @copyright 2019-2023 Team Ever
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use Everblocklight\Tools\Service\EverblocklightTools;

class EverblocklightmodalModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();
    }

    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();
        return $this->getModal();
    }

    protected function getModal()
    {
        $validToken = Tools::getToken();
        if (!Tools::getValue('token') || Tools::getValue('token') != $validToken) {
            Tools::redirect('index.php');
        }
        $blockId = (int) Tools::getValue('id_everblocklight');
        $cmsId = (int) Tools::getValue('id_cms');
        if (!$this->module instanceof Everblocklight) {
            die();
        }
        $module = $this->module;

        if ($cmsId && !$blockId) {
            $cms = new CMS($cmsId, $this->context->language->id, $this->context->shop->id);
            if (!Validate::isLoadedObject($cms) || !(bool) $cms->active) {
                die();
            }
            $cmsContent = EverblocklightTools::renderShortcodes(
                $cms->content,
                $this->context,
                $module
            );
            $this->context->smarty->assign([
                'everblocklight_modal' => (object) ['content' => $cmsContent],
            ]);
            $response = $this->context->smarty->fetch(_PS_MODULE_DIR_ . '/everblocklight/views/templates/front/modal.tpl');
            die($response);
        }
        $block = new EverBlockLightClass(
            $blockId,
            $this->context->language->id,
            $this->context->shop->id
        );
        if (!Validate::isLoadedObject($block)) {
            die();
        }
        $modalDelay = (int) $block->delay;
        $showModal = false;
        $cookieName = $module->encrypt(
            $module->name
            . $this->context->shop->id
            . Configuration::get('PS_SHOP_NAME')
        );
        if ($modalDelay > 0 && (bool) Tools::getValue('force') != true) {
            if (!isset($_COOKIE[$cookieName])) {
                $showModal = true;
                $expiration = time() + ($modalDelay * 24 * 60 * 60);
                setcookie($cookieName, 'true', $expiration, '/');
            }
        } else {
            $showModal = true;
        }
        if ($showModal) {
            $idLang = (int) $this->context->language->id;
            $blockContent = $block->getContent($idLang);
            // Hooks not allowed here
            if (strpos($blockContent, '{hook h=') !== false) {
                $pattern = '/\{hook h=[^}]*\}/';
                $blockContent = preg_replace($pattern, '', $blockContent);
            }
            // Store locator not allowed here
            if (strpos($blockContent, '[storelocator]') !== false) {
                $blockContent = str_replace('[storelocator]', '', $blockContent);
            }
            $blockContent = EverBlockLightTools::renderShortcodes(
                $blockContent,
                $this->context,
                $module
            );
            $this->context->smarty->assign([
                'everblocklight_modal' => (object) [
                    'content' => $blockContent,
                    'background' => $block->background,
                ],
            ]);
            $response = $this->context->smarty->fetch(_PS_MODULE_DIR_ . '/everblocklight/views/templates/front/modal.tpl');
            die($response);
        }
        die();
    }
}
