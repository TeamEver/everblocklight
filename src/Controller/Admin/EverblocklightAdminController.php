<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Controller\Admin;

use Everblocklight\Tools\Command\ClearEverblocklightCacheCommand;
use Everblocklight\Tools\Command\DeleteAdminItemCommand;
use Everblocklight\Tools\Command\SaveAdminItemCommand;
use Everblocklight\Tools\Entity\Block;
use Everblocklight\Tools\Form\BlockType;
use Everblocklight\Tools\Form\EverblocklightConfigurationType;
use Everblocklight\Tools\Form\HookType;
use Everblocklight\Tools\Form\ShortcodeType;
use Everblocklight\Tools\Query\GetAdminItemQuery;
use Everblocklight\Tools\Query\ListAdminItemsQuery;
use Everblocklight\Tools\Repository\BlockRepository;
use Everblocklight\Tools\Repository\HookRepository;
use Everblocklight\Tools\Service\AdminConfigurationManager;
use Everblocklight\Tools\Service\ShortcodeDocumentationProvider;
use Language;
use Module;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EverblocklightAdminController extends FrameworkBundleAdminController
{
    private const SECTION_CONFIG = [
        'blocks' => [
            'title' => 'HTML Blocks',
            'form' => BlockType::class,
            'route' => 'admin_everblocklight_blocks',
            'legacy' => 'AdminEverBlockLight',
            'id' => 'id_everblocklight',
            'columns' => [
                'id_everblocklight',
                'name',
                'hook_name',
                'position',
                'only_home',
                'only_category',
                'only_category_product',
                'only_manufacturer',
                'only_supplier',
                'only_cms_category',
                'date_start',
                'date_end',
                'modal',
                'active',
            ],
            'filter_columns' => [
                'id_everblocklight',
                'name',
                'hook_name',
                'position',
                'only_home',
                'only_category',
                'only_category_product',
                'only_manufacturer',
                'only_supplier',
                'only_cms_category',
                'date_start',
                'date_end',
                'modal',
                'active',
            ],
            'boolean_columns' => [
                'only_home',
                'only_category',
                'only_category_product',
                'only_manufacturer',
                'only_supplier',
                'only_cms_category',
                'modal',
                'active',
            ],
            'column_labels' => [
                'id_everblocklight' => 'ID',
                'name' => 'Name',
                'hook_name' => 'Hook',
                'position' => 'Position',
                'only_home' => 'Home only',
                'only_category' => 'Category only',
                'only_category_product' => 'Product category only',
                'only_manufacturer' => 'Manufacturer only',
                'only_supplier' => 'Supplier only',
                'only_cms_category' => 'CMS category only',
                'date_start' => 'Date start',
                'date_end' => 'Date end',
                'modal' => 'Is modal',
                'active' => 'Status',
            ],
        ],
        'hooks' => [
            'title' => 'Hooks',
            'form' => HookType::class,
            'route' => 'admin_everblocklight_hooks',
            'legacy' => 'AdminEverBlockLightHook',
            'id' => 'id_hook',
            'columns' => ['id_hook', 'name', 'title', 'description', 'active'],
            'filter_columns' => ['id_hook', 'name', 'title', 'description', 'active'],
            'boolean_columns' => ['active'],
            'column_labels' => [
                'id_hook' => 'ID',
                'name' => 'Name',
                'title' => 'Title',
                'description' => 'Description',
                'active' => 'Active',
            ],
        ],
        'shortcodes' => [
            'title' => 'Shortcodes',
            'form' => ShortcodeType::class,
            'route' => 'admin_everblocklight_shortcodes',
            'legacy' => 'AdminEverBlockLightShortcode',
            'id' => 'id_everblocklight_shortcode',
            'columns' => ['id_everblocklight_shortcode', 'shortcode', 'title', 'content'],
            'filter_columns' => ['id_everblocklight_shortcode', 'shortcode', 'title', 'content'],
            'column_labels' => [
                'id_everblocklight_shortcode' => 'ID',
                'shortcode' => 'Shortcode',
                'title' => 'Title',
                'content' => 'Content',
            ],
        ],
    ];

    public function __construct(
        private CommandBusInterface $commandBus,
        private BlockRepository $blockRepository,
        private HookRepository $hookRepository,
        private FormFactoryInterface $formFactory,
        private AdminConfigurationManager $adminConfigurationManager,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function configurationAction(Request $request): Response
    {
        /** @var \Everblocklight $module */
        $module = Module::getInstanceByName('everblocklight');
        $viewContext = $this->adminConfigurationManager->getViewContext($module);
        $formOptions = [
            'holidays' => $viewContext['holidays'],
            'languages' => $viewContext['languages'],
            'stores' => $viewContext['stores'],
        ];
        $form = $this->formFactory->createNamed('', EverblocklightConfigurationType::class, $this->adminConfigurationManager->getFormData($module), $formOptions);
        $form->handleRequest($request);

        if ($request->isMethod('POST') || $request->query->has('deleteEVERBLOCKLIGHT_MARKER_ICON')) {
            if ($request->isMethod('POST') && (!$form->isSubmitted() || !$form->isValid())) {
                $this->addFlash('error', $this->transAdmin('The configuration form could not be validated.'));

                return $this->redirectToRoute('admin_everblocklight_configuration');
            }

            $result = $this->adminConfigurationManager->processRequest($module);
            foreach ($result['errors'] as $error) {
                $this->addFlash('error', $error);
            }
            foreach ($result['success'] as $success) {
                $this->addFlash('success', $success);
            }

            return $this->redirectToRoute('admin_everblocklight_configuration');
        }

        return $this->render('@Modules/everblocklight/templates/admin/configuration.html.twig', [
            'layoutTitle' => 'Ever Block Light',
            'action_buttons' => EverblocklightConfigurationType::actionButtons(),
            'configuration_docs' => EverblocklightConfigurationType::docs(),
            'configuration_form' => $form->createView(),
            'configuration_tabs' => EverblocklightConfigurationType::tabs($viewContext['has_stores']),
            'current_images' => $viewContext['current_images'],
            'field_tabs' => EverblocklightConfigurationType::fieldTabs(
                $viewContext['languages'],
                $viewContext['stores'],
                $viewContext['holidays']
            ),
            'module' => $module,
            'module_version' => $viewContext['module_version'],
            'sections' => self::SECTION_CONFIG,
            'stats' => $viewContext['stats'],
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function listAction(Request $request, string $section): Response
    {
        $config = $this->config($section);
        $filters = $this->extractFilters($request);
        $rows = $this->commandBus->handle(new ListAdminItemsQuery(
            $section,
            $this->shopId(),
            $this->languageId()
        ));
        $filterColumns = $config['filter_columns'] ?? $config['columns'];
        $rows = $this->applyFilters($rows, $filters, $filterColumns, $config['boolean_columns'] ?? []);

        $previewUrls = [];
        if ($section === 'blocks') {
            foreach ($rows as $row) {
                $rowId = (int) ($row[$config['id']] ?? 0);
                if ($rowId > 0) {
                    $previewUrls[$rowId] = $this->buildPreviewUrl($rowId);
                }
            }
        }

        return $this->render('@Modules/everblocklight/templates/admin/list.html.twig', [
            'layoutTitle' => 'Ever Block Light - ' . $config['title'],
            'section' => $section,
            'config' => $config,
            'filters' => $filters,
            'filter_columns' => $filterColumns,
            'rows' => $rows,
            'sections' => self::SECTION_CONFIG,
            'preview_urls' => $previewUrls,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function shortcodeDocumentationAction(): Response
    {
        $module = Module::getInstanceByName('everblocklight');

        return $this->render('@Modules/everblocklight/templates/admin/shortcode_documentation.html.twig', [
            'layoutTitle' => 'Ever Block Light - Shortcode documentation',
            'section' => 'shortcode_documentation',
            'sections' => self::SECTION_CONFIG,
            'documentation' => ShortcodeDocumentationProvider::getDocumentation($module),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function createAction(Request $request, string $section): Response
    {
        return $this->handleForm($request, $section, null);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function editAction(Request $request, string $section, int $id): Response
    {
        return $this->handleForm($request, $section, $id);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteAction(string $section, int $id): RedirectResponse
    {
        $config = $this->config($section);

        $this->commandBus->handle(new DeleteAdminItemCommand($section, $id, $this->shopId()));
        $this->addFlash('success', $this->transAdmin('Item deleted successfully.'));

        return $this->redirectToRoute($config['route']);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function clearCacheAction(Request $request): RedirectResponse
    {
        $this->commandBus->handle(new ClearEverblocklightCacheCommand());
        $this->addFlash('success', $this->transAdmin('Cache cleared successfully.'));

        return $this->redirectToRoute((string) $request->query->get('redirect_route', 'admin_everblocklight_configuration'));
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function toggleBlockAction(int $id): RedirectResponse
    {
        $block = $this->blockRepository->find($id, $this->shopId());
        if ($block === null) {
            $this->addFlash('error', $this->transAdmin('The requested block could not be found.'));

            return $this->redirectToRoute('admin_everblocklight_blocks');
        }

        $this->blockRepository->setActive($id, $this->shopId(), !$block->active);
        $this->clearBlockCache($id, (int) $block->id_hook);
        $this->addFlash('success', $block->active ? $this->transAdmin('Block disabled successfully.') : $this->transAdmin('Block enabled successfully.'));

        return $this->redirectToRoute('admin_everblocklight_blocks');
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function duplicateBlockAction(int $id): RedirectResponse
    {
        $newId = $this->blockRepository->duplicate($id, $this->shopId(), Language::getLanguages(false));
        if ($newId <= 0) {
            $this->addFlash('error', $this->transAdmin('The block could not be duplicated.'));

            return $this->redirectToRoute('admin_everblocklight_blocks');
        }

        $duplicated = $this->blockRepository->find($newId, $this->shopId());
        $this->clearBlockCache($newId, $duplicated ? (int) $duplicated->id_hook : null);
        $this->addFlash('success', $this->transAdmin('Block duplicated successfully.'));

        return $this->redirectToRoute('admin_everblocklight_blocks_edit', ['id' => $newId]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkBlockAction(Request $request, string $bulkAction): RedirectResponse
    {
        $ids = $this->extractBulkIds($request);
        if (empty($ids)) {
            $this->addFlash('error', $this->transAdmin('Please select at least one block.'));

            return $this->redirectToRoute('admin_everblocklight_blocks');
        }

        $count = 0;
        foreach ($ids as $id) {
            $block = $this->blockRepository->find($id, $this->shopId());
            if ($block === null) {
                continue;
            }

            if ($bulkAction === 'enable' || $bulkAction === 'disable') {
                $this->blockRepository->setActive($id, $this->shopId(), $bulkAction === 'enable');
                $this->clearBlockCache($id, (int) $block->id_hook);
                ++$count;
                continue;
            }

            if ($bulkAction === 'delete') {
                $this->commandBus->handle(new DeleteAdminItemCommand('blocks', $id, $this->shopId()));
                ++$count;
                continue;
            }

            if ($bulkAction === 'duplicate') {
                $newId = $this->blockRepository->duplicate($id, $this->shopId(), Language::getLanguages(false));
                if ($newId > 0) {
                    $this->clearBlockCache($newId, (int) $block->id_hook);
                    ++$count;
                }
            }
        }

        $this->addFlash('success', $this->transAdmin('%count% block(s) processed successfully.', ['%count%' => $count]));

        return $this->redirectToRoute('admin_everblocklight_blocks');
    }

    private function handleForm(Request $request, string $section, ?int $id): Response
    {
        $config = $this->config($section);
        $data = $this->commandBus->handle(new GetAdminItemQuery($section, $id, $this->shopId(), $this->languageId()));
        $formOptions = $this->formOptions($section);
        if ($section === 'blocks' && $id === null && empty($data['groups'])) {
            $data['groups'] = array_values(array_map('intval', $formOptions['group_choices']));
        }
        $form = $this->createForm($config['form'], $data, $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            $savedId = $this->commandBus->handle(new SaveAdminItemCommand(
                $section,
                $id,
                $this->shopId(),
                $formData,
                Language::getLanguages(false)
            ));
            $this->addFlash('success', $this->transAdmin('Item saved successfully.'));

            if ($request->request->has('save_and_stay')) {
                return $this->redirectToRoute($config['route'] . '_edit', ['id' => $savedId]);
            }

            return $this->redirectToRoute($config['route']);
        }

        return $this->render('@Modules/everblocklight/templates/admin/form.html.twig', [
            'layoutTitle' => 'Ever Block Light - ' . $config['title'],
            'section' => $section,
            'config' => $config,
            'sections' => self::SECTION_CONFIG,
            'form' => $form->createView(),
            'form_tabs' => $section === 'blocks' ? BlockType::tabs() : [],
            'field_tabs' => $section === 'blocks' ? BlockType::fieldTabs(Language::getLanguages(false)) : [],
            'field_descriptions' => $section === 'blocks' ? BlockType::fieldDescriptions(Language::getLanguages(false)) : [],
            'tab_help' => $section === 'blocks' ? BlockType::tabHelp() : [],
            'tinymce_enabled' => in_array($section, ['blocks', 'shortcodes'], true) && (bool) \Configuration::get('EVERBLOCKLIGHT_TINYMCE'),
            'id' => $id,
            'preview_url' => ($section === 'blocks' && $id !== null && $id > 0) ? $this->buildPreviewUrl((int) $id) : null,
        ]);
    }

    private function buildPreviewUrl(int $blockId): string
    {
        if ($blockId <= 0) {
            return '';
        }

        $context = \Context::getContext();
        if (!$context || !$context->link) {
            return '';
        }

        $params = [
            'id_everblocklight' => $blockId,
            'id_lang' => $this->languageId(),
            'id_shop' => $this->shopId(),
            'token' => \Tools::getAdminTokenLite('AdminEverBlockLight'),
        ];

        return (string) $context->link->getModuleLink('everblocklight', 'preview', $params);
    }

    private function formOptions(string $section): array
    {
        $options = ['languages' => Language::getLanguages(false)];
        if ($section === 'blocks') {
            $options['hook_choices'] = ['Choose a hook' => 0] + $this->hookRepository->choices();
            $options['category_choices'] = $this->categoryChoices();
            $options['manufacturer_choices'] = $this->manufacturerChoices();
            $options['supplier_choices'] = $this->supplierChoices();
            $options['cms_category_choices'] = $this->cmsCategoryChoices();
            $options['group_choices'] = $this->groupChoices();
        }

        return $options;
    }

    private function categoryChoices(): array
    {
        $choices = [];
        foreach (\Category::getCategories(false, true, false) as $category) {
            $id = (int) $category['id_category'];
            $choices[$id . ' - ' . (string) $category['name']] = $id;
        }

        return $choices;
    }

    private function manufacturerChoices(): array
    {
        $choices = [];
        foreach (\Manufacturer::getLiteManufacturersList($this->languageId()) as $manufacturer) {
            $id = (int) ($manufacturer['id'] ?? $manufacturer['id_manufacturer'] ?? 0);
            if ($id > 0) {
                $choices[(string) $manufacturer['name']] = $id;
            }
        }

        return $choices;
    }

    private function supplierChoices(): array
    {
        $choices = [];
        foreach (\Supplier::getLiteSuppliersList($this->languageId()) as $supplier) {
            $id = (int) ($supplier['id'] ?? $supplier['id_supplier'] ?? 0);
            if ($id > 0) {
                $choices[(string) $supplier['name']] = $id;
            }
        }

        return $choices;
    }

    private function cmsCategoryChoices(): array
    {
        $choices = [];
        foreach (\CMSCategory::getSimpleCategories($this->languageId()) as $cmsCategory) {
            $id = (int) $cmsCategory['id_cms_category'];
            $choices[(string) $cmsCategory['name']] = $id;
        }

        return $choices;
    }

    private function groupChoices(): array
    {
        $choices = [];
        foreach (\Group::getGroups($this->languageId()) as $group) {
            $choices[(string) $group['name']] = (int) $group['id_group'];
        }

        return $choices;
    }

    private function extractFilters(Request $request): array
    {
        $queryParameters = $request->query->all();
        $rawFilters = $queryParameters['filters'] ?? [];
        if (!is_array($rawFilters)) {
            return [];
        }

        $filters = [];
        foreach ($rawFilters as $field => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '') {
                $filters[(string) $field] = $value;
            }
        }

        return $filters;
    }

    private function applyFilters(array $rows, array $filters, array $allowedColumns, array $booleanColumns): array
    {
        if (empty($filters)) {
            return $rows;
        }

        $allowed = array_flip($allowedColumns);
        $booleans = array_flip($booleanColumns);

        return array_values(array_filter($rows, static function (array $row) use ($filters, $allowed, $booleans): bool {
            foreach ($filters as $field => $expected) {
                if (!isset($allowed[$field])) {
                    continue;
                }

                $actual = $row[$field] ?? '';
                if (isset($booleans[$field])) {
                    if ((string) (int) (bool) $actual !== (string) (int) $expected) {
                        return false;
                    }
                    continue;
                }

                if (stripos(strip_tags((string) $actual), (string) $expected) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function extractBulkIds(Request $request): array
    {
        $values = array_merge($request->request->all(), $request->query->all());
        $ids = [];
        $this->collectIds($values, $ids);

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function collectIds(array $values, array &$ids): void
    {
        foreach ($values as $key => $value) {
            $key = (string) $key;
            if (is_array($value)) {
                if (preg_match('/(^|_)(ids?|selected|bulk_action_selected)(_|$)/i', $key)) {
                    array_walk_recursive($value, static function ($item) use (&$ids): void {
                        if (is_scalar($item)) {
                            $ids[] = (int) $item;
                        }
                    });
                } else {
                    $this->collectIds($value, $ids);
                }
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            if (preg_match('/(^|_)(ids?|selected|bulk_action_selected)(_|$)/i', $key)) {
                $ids[] = (int) $value;
            }
        }
    }

    private function clearBlockCache(?int $blockId = null, ?int $hookId = null): void
    {
        Block::clearCache($blockId, $this->shopId(), Language::getLanguages(false), $hookId !== null && $hookId > 0 ? [$hookId] : []);
    }

    private function transAdmin(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, 'Modules.Everblocklight.Admin');
    }

    private function config(string $section): array
    {
        if (!isset(self::SECTION_CONFIG[$section])) {
            throw $this->createNotFoundException(sprintf('Unknown Everblocklight admin section "%s".', $section));
        }

        return self::SECTION_CONFIG[$section];
    }

    private function shopId(): int
    {
        return (int) \Context::getContext()->shop->id;
    }

    private function languageId(): int
    {
        return (int) \Context::getContext()->language->id;
    }
}
