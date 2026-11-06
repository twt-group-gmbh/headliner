<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\View\PageViewMode;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Twtgroup\Headliner\Backend\SelectedLanguages;
use Twtgroup\Headliner\Backend\StructureBox;

/**
 * Puts the box with the heading structure of the page above the content of the page module. Only a placeholder: the
 * page is checked afterwards (`StructureController`, loaded by `headlines.js`), so the page module is not delayed by
 * the rendering of the frontend.
 */
#[AsEventListener(identifier: 'headliner/page-module-headlines')]
final readonly class PageModuleHeadlines
{
    public function __construct(
        private StructureBox $box,
        private ViewFactoryInterface $viewFactory,
        private PageRenderer $pageRenderer,
        private UriBuilder $uriBuilder,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        $pageId = (int) ($request->getQueryParams()['id'] ?? 0);
        if (!$this->box->supports($pageId)) {
            return;
        }

        $this->pageRenderer->addCssFile('EXT:headliner/Resources/Public/Css/PageModule.css');
        $this->pageRenderer->loadJavaScriptModule('@twtgroup/headliner/headlines.js');
        // the edit links of the box are loaded afterwards; the element they are is defined here, once for the page
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/element/contextual-record-edit-trigger.js');

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:headliner/Resources/Private/Templates'],
            request: $request,
        ));
        $view->assignMultiple([
            'url' => (string) $this->uriBuilder->buildUriFromRoute(StructureBox::ROUTE, [
                'id' => $pageId,
                'languages' => $this->getLanguageIds($request),
            ]),
            'collapsed' => $this->box->isCollapsed(),
        ]);
        $event->addHeaderContent($view->render('PageModule/Placeholder'));
    }

    /**
     * @return list<int>
     */
    private function getLanguageIds(ServerRequestInterface $request): array
    {
        $moduleData = $request->getAttribute('moduleData');
        if (!$moduleData instanceof ModuleData) {
            return [0];
        }

        return SelectedLanguages::forView(
            $moduleData->get('languages'),
            PageViewMode::tryFrom((int) $moduleData->get('viewMode')) === PageViewMode::LanguageComparisonView
        );
    }
}
