<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Service\EverblocklightTools;

final class SeoToolsTest extends TestCase
{
    public function testObfuscateTextReplacesLinksBySpans(): void
    {
        $html = EverblocklightTools::obfuscateText('<a class="btn" href="https://griiv.fr/contact" target="_blank">Contact</a>');

        self::assertStringContainsString('<span class="btn obflink" data-obflink="' . base64_encode('https://griiv.fr/contact') . '"', $html);
        self::assertStringContainsString(' target="_blank">Contact', $html);
        self::assertStringNotContainsString('<a ', $html);
    }

    public function testObfuscateTextWithoutLinkIsUnchanged(): void
    {
        self::assertSame('<p>Pas de lien</p>', EverblocklightTools::obfuscateText('<p>Pas de lien</p>'));
    }

    public function testObfuscateByClassIsDisabledByDefault(): void
    {
        $html = '<a class="obfme" href="/cgv">CGV</a>';

        self::assertSame($html, EverblocklightTools::obfuscateTextByClass($html));
    }

    public function testObfuscateByClassOnlyTargetsObfmeLinks(): void
    {
        \Configuration::$values['EVERBLOCKLIGHT_USE_OBF'] = '1';

        $html = EverblocklightTools::obfuscateTextByClass(
            '<a class="link obfme" href="/cgv">CGV</a> <a class="link" href="/faq">Aide</a>'
        );

        self::assertStringContainsString('<span class="link obfme obflink" data-obflink="' . base64_encode('/cgv') . '">CGV</span>', $html);
        self::assertStringContainsString('<a class="link" href="/faq">Aide</a>', $html);
    }

    public function testObfuscateByClassKeepsOtherAttributes(): void
    {
        \Configuration::$values['EVERBLOCKLIGHT_USE_OBF'] = '1';

        self::assertSame(
            '<span class="btn obfme obflink" title="CGV" target="_blank" data-obflink="' . base64_encode('/cgv') . '"><b>CGV</b></span>',
            EverblocklightTools::obfuscateTextByClass('<a title="CGV" class=\'btn obfme\' href="/cgv" target="_blank"><b>CGV</b></a>')
        );
    }

    public function testLazyloadAddsClassAndLoadingAttribute(): void
    {
        self::assertSame(
            '<img alt="Logo" class="lazyload" loading="lazy" src="/img/logo.png">',
            EverblocklightTools::addLazyLoadToImages('<img alt="Logo" src="/img/logo.png">')
        );
    }

    public function testLazyloadKeepsExistingClassAndLoading(): void
    {
        $html = EverblocklightTools::addLazyLoadToImages('<img class="img-fluid" loading="eager" src="/a.jpg">');

        self::assertStringContainsString('class="img-fluid lazyload"', $html);
        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringNotContainsString('loading="lazy"', $html);
    }
}
