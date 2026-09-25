<?php

/**
 * Exécuté dans le conteneur PrestaShop par tests/install/run.sh (php < create_fixtures.php).
 * Crée un shortcode et un bloc via les entités/repositories du module (même chemin DBAL que l'admin).
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

// Charge le module (et donc son autoloader Everblocklight\Tools\...)
if (!Module::getInstanceByName('everblocklight')) {
    fwrite(STDERR, "Module everblocklight introuvable\n");
    exit(1);
}

use Everblocklight\Tools\Entity\Block;
use Everblocklight\Tools\Entity\Shortcode;

$languages = Language::getLanguages(false);

$shortcode = new Shortcode();
$shortcode->shortcode = '[ci_shortcode]';
$shortcode->id_shop = 1;
foreach ($languages as $language) {
    $shortcode->title[(int) $language['id_lang']] = 'CI';
    $shortcode->content[(int) $language['id_lang']] = 'CI-SHORTCODE-OK';
}
if (!$shortcode->save()) {
    fwrite(STDERR, "Enregistrement du shortcode impossible\n");
    exit(1);
}

$block = new Block();
$block->name = 'ci-front';
$block->id_hook = (int) Hook::getIdByName('displayHome');
$block->only_home = true;
$block->id_shop = 1;
$block->position = 99;
$block->groups = '[]';
$block->active = true;
foreach ($languages as $language) {
    $block->content[(int) $language['id_lang']] = '<div id="ci-everblocklight">[alert type="success"]CI-ALERT-OK[/alert] [ci_shortcode]</div>';
    $block->custom_code[(int) $language['id_lang']] = '';
}
if (!$block->save() || (int) $block->id <= 0) {
    fwrite(STDERR, "Enregistrement du bloc impossible\n");
    exit(1);
}

// Relecture : vérifie l'aller-retour en base (colonnes, traductions)
$reloaded = new Block((int) $block->id, null, 1);
if ($reloaded->name !== 'ci-front' || $reloaded->groups !== '[]' || strpos($reloaded->getContent(), 'CI-ALERT-OK') === false) {
    fwrite(STDERR, "Relecture du bloc incohérente\n");
    exit(1);
}

echo 'OK block=' . (int) $block->id . ' shortcode=' . (int) $shortcode->id . PHP_EOL;
