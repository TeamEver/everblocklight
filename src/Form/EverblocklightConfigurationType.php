<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EverblocklightConfigurationType extends AbstractType
{
    public static function tabs(bool $hasStores): array
    {
        $tabs = [
            'settings' => 'Réglages',
            'meta_tools' => 'Meta Tools',
            'wordpress_tools' => 'WordPress Tools',
            'google_maps' => 'Google Tools',
            'tools' => 'Outils',
        ];

        if ($hasStores) {
            $tabs['holiday'] = 'Holiday opening hours by store';
        }

        $tabs['cron'] = 'Tâches crons';

        return $tabs;
    }

    public static function fieldTabs(array $languages, array $stores, array $holidays, bool $hasInstagramToken): array
    {
        $fieldTabs = [
            'settings' => [
                'EVERBLOCKLIGHT_OPTIONS_POSITION',
                'EVERBLOCKLIGHT_LOAD_FRONT_CSS',
                'EVERBLOCKLIGHT_USE_OBF',
                'EVERBLOCKLIGHT_TINYMCE',
                'EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER',
                'EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER',
            ],
            'meta_tools' => [
                'EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN',
            ],
            'wordpress_tools' => [
                'EVERBLOCKLIGHT_WP_API_URL',
                'EVERBLOCKLIGHT_WP_BLOG_URL',
                'EVERBLOCKLIGHT_WP_POST_NBR',
                'EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE',
            ],
            'google_maps' => [
                'EVERBLOCKLIGHT_GOOGLE_API_KEY',
                'EVERBLOCKLIGHT_GOOGLE_PLACE_ID',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_LABEL',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL',
                'EVERBLOCKLIGHT_GMAP_KEY',
                'EVERBLOCKLIGHT_MARKER_ICON',
                'EVERBLOCKLIGHT_STORELOCATOR_TOGGLE',
            ],
            'tools' => [
                'EVERBLOCKLIGHT_CSS',
                'EVERBLOCKLIGHT_JS',
                'EVERBLOCKLIGHT_CSS_LINKS',
                'EVERBLOCKLIGHT_JS_LINKS',
                'EVERBLOCKLIGHT_HEADER_SCRIPTS',
            ],
            'holiday' => [],
            'cron' => [],
        ];

        foreach ($languages as $language) {
            array_unshift($fieldTabs['settings'], 'EVERBLOCKLIGHT_OPTIONS_TITLE_' . (int) $language['id_lang']);
        }

        if ($hasInstagramToken) {
            $fieldTabs['meta_tools'][] = 'EVERBLOCKLIGHT_INSTA_LINK';
            $fieldTabs['meta_tools'][] = 'EVERBLOCKLIGHT_INSTA_SHOW_CAPTION';
        }

        foreach ($stores as $store) {
            foreach ($holidays as $date) {
                $fieldTabs['holiday'][] = 'EVERBLOCKLIGHT_HOLIDAY_HOURS_' . (int) $store['id_store'] . '_' . $date;
            }
        }

        return $fieldTabs;
    }

    public static function actionButtons(): array
    {
        return [
            'tools' => [
                ['name' => 'submitEmptyCache', 'title' => 'Empty Everblocklight cache', 'icon' => 'cached'],
            ],
        ];
    }

    public static function docs(): array
    {
        return [
            'settings' => 'Configure global behavior: checkout step, front assets, editor and generated content defaults.',
            'meta_tools' => 'Configure Meta integrations, including Instagram access and display options.',
            'wordpress_tools' => 'Configure the WordPress REST endpoint and the latest posts block.',
            'google_maps' => 'Configure Google Places reviews, Google Maps keys and store locator marker options.',
            'tools' => 'Custom CSS / JS assets and cache cleanup.',
            'holiday' => 'Override holiday opening hours per store.',
            'cron' => 'Use these secure URLs to run Everblocklight maintenance tasks from cron.',
        ];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['languages'] as $language) {
            $langId = (int) $language['id_lang'];
            $label = (string) ($language['iso_code'] ?? $langId);
            $builder->add('EVERBLOCKLIGHT_OPTIONS_TITLE_' . $langId, TextType::class, [
                'label' => 'New order step title (' . $label . ')',
                'required' => false,
                'help' => 'If not set, new order step will not be shown.',
            ]);
        }

        $builder
            ->add('EVERBLOCKLIGHT_OPTIONS_POSITION', ChoiceType::class, [
                'label' => 'New order step position',
                'choices' => [
                    'After login' => 1,
                    'After address form' => 2,
                    'After shipping form' => 3,
                ],
                'required' => false,
                'attr' => [
                    'class' => 'everblocklight-enhanced-select',
                    'data-everblocklight-placeholder' => 'Search position',
                ],
            ]);

        $this->addSwitch($builder, 'EVERBLOCKLIGHT_LOAD_FRONT_CSS', 'Load everblocklight.css on the front office ?');
        $this->addSwitch($builder, 'EVERBLOCKLIGHT_USE_OBF', 'Enable front-office script for obfuscation ?');
        $this->addSwitch($builder, 'EVERBLOCKLIGHT_TINYMCE', 'Extends TinyMCE on blocks management ?');

        $builder
            ->add('EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER', TextType::class, [
                'label' => 'Default number of paragraphs when [llorem] shortcode is detected',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER', TextType::class, [
                'label' => 'Default number of sentences per paragraphs when [llorem] shortcode is detected',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN', TextType::class, [
                'label' => 'Instagram access token',
                'required' => false,
            ]);

        if ($options['has_instagram_token']) {
            $builder
                ->add('EVERBLOCKLIGHT_INSTA_LINK', TextType::class, [
                    'label' => 'Instagram profile link',
                    'required' => false,
                ]);
            $this->addSwitch($builder, 'EVERBLOCKLIGHT_INSTA_SHOW_CAPTION', 'Display Instagram post text');
        }

        $builder
            ->add('EVERBLOCKLIGHT_WP_API_URL', TextType::class, [
                'label' => 'WordPress API URL',
                'required' => false,
                'help' => 'Example: https://example.com/wp-json/wp/v2/posts',
            ])
            ->add('EVERBLOCKLIGHT_WP_BLOG_URL', TextType::class, [
                'label' => 'Blog URL',
                'required' => false,
                'help' => 'Use an absolute URL or a relative path such as /blog.',
            ])
            ->add('EVERBLOCKLIGHT_WP_POST_NBR', TextType::class, [
                'label' => 'Number of blog posts to display',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE', FileType::class, [
                'label' => 'Background image for WordPress posts',
                'required' => false,
                'mapped' => false,
                'help' => 'Optional background image for the latest WordPress posts section.',
            ])
            ->add('EVERBLOCKLIGHT_GOOGLE_API_KEY', TextType::class, [
                'label' => 'Google Places API key',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_GOOGLE_PLACE_ID', TextType::class, [
                'label' => 'Google Place ID',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT', TextType::class, [
                'label' => 'Maximum number of reviews',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING', TextType::class, [
                'label' => 'Minimum rating to display',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT', ChoiceType::class, [
                'label' => 'Reviews sort order',
                'choices' => [
                    'Most relevant' => 'most_relevant',
                    'Most recent' => 'newest',
                ],
                'required' => false,
                'attr' => [
                    'class' => 'everblocklight-enhanced-select',
                    'data-everblocklight-placeholder' => 'Search sort order',
                ],
            ]);

        $this->addSwitch($builder, 'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING', 'Show overall rating');
        $this->addSwitch($builder, 'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR', 'Show reviewer photos');
        $this->addSwitch($builder, 'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA', 'Show call-to-action button');

        $builder
            ->add('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_LABEL', TextType::class, [
                'label' => 'CTA label',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL', TextType::class, [
                'label' => 'CTA link override',
                'required' => false,
                'help' => 'Leave empty to use the Google listing URL.',
            ])
            ->add('EVERBLOCKLIGHT_GMAP_KEY', TextType::class, [
                'label' => 'Google Map API key (CMS page only)',
                'required' => false,
            ])
            ->add('EVERBLOCKLIGHT_MARKER_ICON', FileType::class, [
                'label' => 'Store locator marker icon',
                'required' => false,
                'mapped' => false,
                'help' => 'Only SVG files are allowed.',
            ]);

        $this->addSwitch($builder, 'EVERBLOCKLIGHT_STORELOCATOR_TOGGLE', 'Display map toggle button');

        $builder
            ->add('EVERBLOCKLIGHT_CSS', TextareaType::class, [
                'label' => 'Code CSS personnalisé',
                'required' => false,
                'attr' => ['rows' => 10, 'class' => 'everblocklight-code'],
            ])
            ->add('EVERBLOCKLIGHT_JS', TextareaType::class, [
                'label' => 'Javascript / jQuery personnalisé',
                'required' => false,
                'attr' => ['rows' => 10, 'class' => 'everblocklight-code'],
            ])
            ->add('EVERBLOCKLIGHT_CSS_LINKS', TextareaType::class, [
                'label' => 'Liens CSS personnalisés',
                'required' => false,
                'attr' => ['rows' => 5],
                'help' => 'Add one link per line, must be CSS.',
            ])
            ->add('EVERBLOCKLIGHT_JS_LINKS', TextareaType::class, [
                'label' => 'Liens javascript personnalisés',
                'required' => false,
                'attr' => ['rows' => 5],
                'help' => 'Add one link per line, must be JS.',
            ])
            ->add('EVERBLOCKLIGHT_HEADER_SCRIPTS', TextareaType::class, [
                'label' => 'Header scripts',
                'required' => false,
                'attr' => ['rows' => 7],
            ]);

        foreach ($options['stores'] as $store) {
            foreach ($options['holidays'] as $date) {
                $builder->add('EVERBLOCKLIGHT_HOLIDAY_HOURS_' . (int) $store['id_store'] . '_' . $date, TextType::class, [
                    'label' => sprintf('Holiday hours for %s on %s', $store['name'], $date),
                    'required' => false,
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_extra_fields' => true,
            'csrf_protection' => true,
            'has_instagram_token' => false,
            'holidays' => [],
            'languages' => [],
            'stores' => [],
            'translation_domain' => 'Modules.Everblocklight.Admin',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }

    private function addSwitch(FormBuilderInterface $builder, string $name, string $label): void
    {
        $builder->add($name, ChoiceType::class, [
            'label' => $label,
            'choices' => [
                'Enabled' => 1,
                'Disabled' => 0,
            ],
            'expanded' => true,
            'multiple' => false,
            'required' => true,
        ]);
    }

}
