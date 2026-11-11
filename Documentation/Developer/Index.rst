..  _developer:

=========
Developer
=========

..  _developer-how-it-works:

How the structure is checked
============================

#.  `PageModuleHeadlines` listens to the PSR-14 event `ModifyPageLayoutContentEvent` of the page module. It only adds a
    placeholder (the template :file:`Resources/Private/Templates/PageModule/Placeholder.html`), so the page module is not
    delayed. :file:`headlines.js` then loads the box from the AJAX route `headliner_structure`
    (`StructureController`, which checks that the backend user may see the page), also while the box is collapsed.
    `StructureBox` does the work: for every language that the page module shows (`SelectedLanguages`, which follows
    the page module, passed as `languages` to the route) it renders the page.
#.  `PageHtmlFetcher` renders the page with a sub-request through the core's `RequestHandler`, in the running PHP
    process. The frontend middleware changes `BE_USER`, `TYPO3_REQUEST`, `LANG`, the Context aspects and the
    `PageRenderer`; the fetcher puts them back.
#.  The request carries the request attribute `headliner.inspect`. The frontend middleware `InspectRequest` then switches
    the page cache off for it, includes hidden pages in the visibility of the request (it has to run before the page is
    resolved, which is where a hidden page is turned down) and, with the attribute `headliner.inspect.time`, renders it at
    that time (the date aspect and `SIM_ACCESS_TIME`, as the preview of the core does). With `headliner.inspect.groups` it
    renders the page as a member of those frontend user groups (the aspect `frontend.user`, so it has to run after the
    frontend user is authenticated), and with `headliner.inspect.workspace` in a workspace (the aspect `workspace`, which
    the repositories of the frontend read to overlay versions). The ViewHelpers then add the marks to the headings:
    `data-headliner-edit="table:uid:field"` (`headliner:headline` from the record, `headliner:headline.values` from `edit`)
    and `data-headliner-element`. The attribute exists only inside the process, so a request from outside cannot set it.
#.  `HeadingExtractor` reads the headings (`h1` to `h6`) and the marked headers from the HTML, and the content element
    each is rendered in: what the heading says itself (`data-headliner-element`), else the frame `id="c12"`. It reads the
    record a heading in a rich text is written in from `data-headliner-record` on the heading itself, never from an element
    around it (the page is written by editors). It marks the headings that are not in the elements of the site setting
    `headliner.contentSelector` as outside; `symfony/css-selector` turns the CSS selector into the XPath the DOM needs.
#.  `HeadingValidator` finds the problems. Then the event `ModifyHeadingIssuesEvent` is dispatched, so a project can add
    rules of its own or take built-in ones away (see :ref:`developer-rules`).
#.  A heading from the text is matched with its place in the field `bodytext` by `RteHeadings`: the n-th heading of the
    text, in the order the frontend renders them (the box counts the headings of an element, `data-headliner-rte="uid:n"`).
    The select sends the place, the new tag and the text it showed to the AJAX route `headliner_rte_tag`
    (`RteHeadingController`); `RteHeadingEditor` checks the rights, that the tag is one the editor offers (`heading.options` of
    the RTE configuration from `Richtext::getConfiguration()`, in the select and again on saving) and that the text at that place is the same, changes
    only the tag (`<hN>` and `</hN>`, the attributes stay) and saves the field through the DataHandler. If the text is not
    the same, nothing is changed.
#.  The marks for a record that is not the content element come from two places. `MarkRichTextHeadings`, a listener on the
    core's `RecordCreationEvent`, writes `data-headliner-record="table:uid"` and `data-headliner-field` into every heading
    of the rich text fields of a record object, when the record is created for a request with `headliner.inspect`: it
    does not matter how a template renders the text. For a row as an array there is no record to create, so
    `headliner:text` puts the same mark into the headings of the text (`HeadingMark`), with the table from the row or from
    the inline field of the content element (`RowTable`) and the one rich text field of the table.
    `StructureBox` then finds the rich text of each such heading in the record (`richTextOf()`: the first heading of a
    rich text field with the same level and text) and, for a record whose heading names no element, the content element it is linked to
    from the inline field of `tt_content` (`elementOfRecord()`). The box only believes a mark for a field that
    `Schema\TagField` says is a select of tags, on its own page, and for a table the user may edit.
#.  `EditAccess` decides what the backend user may do with each element, and the Fluid template
    :file:`Resources/Private/Templates/PageModule/Headlines.html` draws the box.

..  _developer-rules:

Your own rules
==============

The box checks four things: a missing H1, more than one H1, a skipped level and an empty heading. A project can change
that with the PSR-14 event `Twtgroup\Headliner\Event\ModifyHeadingIssuesEvent`. It is dispatched after the built-in
rules have run, once for each language the box shows, while the page module loads the box.

The event gives you the headings of the page (`getHeadings()`, in the order of the page, each with `level`, `text` and
`outside`), the page and the language (`getPageId()`, `getLanguageId()`) and the issues found so far (`getIssues()`). You
can:

*   **add** an issue: `addIssue(string $type, ?int $index, ?string $label)`. `$type` is a name, prefix it with your
    extension key (`myext.tooLong`). `$index` is the position of the heading in `getHeadings()`, or `null` for a problem of
    the page as a whole. `$label` is the text of the badge, plain text or an `LLL:` reference; the box prints it as text,
    so markup in it is not interpreted. The issue is counted in the summary like the others.
*   **remove** the issues of a rule: `removeIssues('multipleH1')` (the names of the built-in rules are `missingH1`,
    `multipleH1`, `skippedLevel` and `emptyHeading`). A site that uses more than one H1 on purpose switches that rule off this
    way.
*   **replace** all issues with `setIssues()`.

..  code-block:: php
    :caption: Classes/EventListener/HeadingTooLong.php

    use Twtgroup\Headliner\Event\ModifyHeadingIssuesEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    #[AsEventListener]
    final class HeadingTooLong
    {
        public function __invoke(ModifyHeadingIssuesEvent $event): void
        {
            foreach ($event->getHeadings() as $index => $heading) {
                if (!$heading->outside && mb_strlen($heading->text) > 70) {
                    $event->addIssue('myext.tooLong', $index, 'LLL:EXT:myext/Resources/Private/Language/locallang.xlf:headingTooLong');
                }
            }
        }
    }

What comes back is checked: an issue without a name, or with an index that is not a heading of the page, is ignored. If a
listener throws an exception, the box is drawn with the issues of the built-in rules, without what the listeners did, and the
exception is written to the log (level error, with the exception in the context), so a broken listener does not take the box
down. A listener that is slow cannot be stopped (PHP cannot interrupt it in a web request), the box waits for it; if the
listeners take 500 milliseconds or more together, a warning with the time is written to the log. The listener runs every time somebody opens the page in the page module, so keep it quick: the headings are extracted already,
do not render or fetch anything in it. A listener does not change what visitors see, only the box.

..  _developer-rules-logging:

Logging
-------

The box writes two things to the log, with the logger `Twtgroup.Headliner.Backend.StructureBox`: an `error` if a listener
throws an exception, and a `warning` if the listeners take 500 milliseconds or more together. TYPO3 writes everything from
`warning` up to a file in :file:`var/log/` by default, also in the context Production. Nothing else is logged on a normal
call of the box.

The log configuration of TYPO3 decides what happens to it. The most specific configuration replaces the general one, so a
project can set it for the extension (the key `Twtgroup`, `Headliner`) in :file:`config/system/additional.php`:

..  code-block:: php
    :caption: config/system/additional.php

    use Psr\Log\LogLevel;
    use TYPO3\CMS\Core\Log\Writer\FileWriter;

    // only errors (a listener that throws), no warnings about slow listeners
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Twtgroup']['Headliner']['writerConfiguration'] = [
        LogLevel::ERROR => [FileWriter::class => []],
    ];

..  code-block:: php
    :caption: config/system/additional.php

    // nothing at all: a level with a writer that is switched off
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Twtgroup']['Headliner']['writerConfiguration'] = [
        LogLevel::WARNING => [FileWriter::class => ['disabled' => true]],
    ];

..  code-block:: php
    :caption: config/system/additional.php

    // also in the table sys_log, where the Log module of the backend shows it
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Twtgroup']['Headliner']['writerConfiguration'] = [
        LogLevel::WARNING => [
            FileWriter::class => [],
            \TYPO3\CMS\Core\Log\Writer\DatabaseWriter::class => [],
        ],
    ];

An empty array does nothing: TYPO3 then takes the general configuration. A listener that is slow all the time writes one
line for every call of the box, which is a sign to make the listener faster.

..  _developer-javascript:

JavaScript
==========

:file:`Resources/Public/JavaScript/headlines.js` saves the collapsed state with `Persistent` and changes tags with
`@typo3/backend/ajax-data-handler.js`, then loads the box again. It also loads the box when the page module is shown (see above), and again when the page module changes content without a reload: the event `typo3:page-layout:hidden-content-count-changed` (hide and show of an element) and `typo3:datahandler:process` (drag and drop). Opening an element uses the core's
`be:link.editRecord` with `contextual="true"`; nothing builds edit URLs by hand.

..  _developer-tests:

Tests
=====

..  code-block:: bash

    ddev test unit
    ddev test functional
    ddev test js
    ddev lint

The JavaScript tests (`ddev test js`, Vitest with jsdom) cover :file:`headlines.js`: loading the box, the reload after
changes, collapsing, and changing a tag. The unit tests need no TYPO3 bootstrap. The functional tests render the frontend through the real application, with the
site sets of `fluid_styled_content` and of the extension, and use the testing framework: the tag and layout of the
headers, the subheader and its site setting, the marks for the page module (which a visitor and a request with a header
from outside must never get), the page cache, the fields and the sub-request of the page module.

..  code-block:: bash

    ddev docs

renders this documentation with the TYPO3 documentation toolchain (Docker). Fix every warning it prints.
