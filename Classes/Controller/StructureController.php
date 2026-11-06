<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Twtgroup\Headliner\Backend\LanguageAccess;
use Twtgroup\Headliner\Backend\StructureBox;

/**
 * AJAX route that gives the box with the heading structure of a page. The page module shows a placeholder first and
 * loads the box from here, so the rendering of the frontend does not delay the page module.
 */
final readonly class StructureController
{
    public function __construct(private StructureBox $box) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $pageId = (int) ($query['id'] ?? 0);
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        // the same right as for the page module itself: the user may see the page
        if ($pageId <= 0 || !$backendUser instanceof BackendUserAuthentication
            || !is_array(BackendUtility::readPageAccess($pageId, $backendUser->getPagePermsClause(Permission::PAGE_SHOW)))
        ) {
            return new Response(null, 403);
        }

        // only the languages the user may use: the page module shows those, but this route takes them from the request
        $languageIds = LanguageAccess::filter(array_values(array_map(intval(...), (array) ($query['languages'] ?? [0]))), $backendUser);
        // only a local URL: the edit forms return there
        $returnUrl = GeneralUtility::sanitizeLocalUrl((string) ($query['returnUrl'] ?? ''), $request);

        // an empty body: there is nothing to show, the placeholder goes away
        return new HtmlResponse($this->box->render($request, $pageId, $languageIds, $returnUrl));
    }
}
