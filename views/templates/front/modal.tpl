{*
 * 2019-2025 Team Ever
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
 *  @copyright 2019-2025 Team Ever
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}

{if isset($everblocklight_modal) && $everblocklight_modal}
<div class="modal fade everblocklightModal" id="everblocklightModal" tabindex="-1" role="dialog" aria-labelledby="everblocklightModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered everblocklight-modal-dialog" role="document">
        <div class="modal-content"
            {if isset($everblocklight_modal->background) && $everblocklight_modal->background}
            style="background-color:{$everblocklight_modal->background|escape:'htmlall':'UTF-8'};"
            {/if}>
            {* SEO : modal must have titles *}
            <p id="everblocklightModalLabel" class="h5 modal-title d-none">
                {l s='Modal' d='Modules.Everblocklight.Front'}
            </p>
            <!-- Contenu de la modal -->
            <div class="modal-body">
                <!-- Bouton de fermeture aligne a droite -->
                <button type="button" class="close float-right" data-bs-dismiss="modal" data-dismiss="modal" aria-label="{l s='Close' d='Modules.Everblocklight.Front'}">
                    <span aria-hidden="true">&times;</span>
                </button>
                {$everblocklight_modal->content nofilter}
            </div>
        </div>
    </div>
</div>
{/if}

