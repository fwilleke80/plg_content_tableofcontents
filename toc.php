<?php
// No direct access
defined('_JEXEC') or die;

/**
 * Plugin to generate a Table of Contents (TOC) in articles.
 *
 * Usage:
 *   {toc}
 *   {toc maxlevel=2 chapternumbers=true}
 *
 * The plugin scans the article for header tags (h1 to h6), inserts an anchor before each header,
 * and replaces the {toc} tag with a nested list linking to the headers.
 *
 * If the parameter "chapternumbers" is true the plugin prefixes header text (and the anchors)
 * with a chapter numbering (e.g. "1. " for h1, "1.1. " for h2, etc.).
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.Toc
 * @version     1.0.0
 * @author      Your Name
 * @license     GNU/GPL
 */
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentToc extends CMSPlugin
{
    /**
     * Prepare content method.
     *
     * This event is triggered when content is being prepared.
     *
     * @param   string  $context     The context of the content being passed to the plugin.
     * @param   object  &$article    The article object. (The article text is in $article->text.)
     * @param   object  &$params     The article parameters.
     * @param   int     $limitstart  The 'page' number.
     *
     * @return  void
     */
    public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
    {
        if (!isset($article->text))
        {
            return;
        }

        $content = $article->text;

        // Look for the {toc ...} tag in the content.
        if (!preg_match('/\{toc(?:\s+(.*?))?\}/i', $content, $tocMatch))
        {
            // No TOC marker found.
            return;
        }

        // Parse parameters (if any) provided in the {toc ...} tag.
        $tocParams = $this->parseParams(isset($tocMatch[1]) ? $tocMatch[1] : '');
        $maxLevel = isset($tocParams['maxlevel']) ? (int)$tocParams['maxlevel'] : 6;
        $chapterNumbers = isset($tocParams['chapternumbers']) && strtolower($tocParams['chapternumbers']) === 'true';

        // Find all header tags in the content.
        $headers = array();
        $pattern = '/<(h[1-6])([^>]*)>(.*?)<\/\1>/i';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        // Used for chapter numbering.
        $chapterCount = array();

        // Process each header found.
        foreach ($matches as $match)
        {
            $tag = $match[1];         // e.g. h1, h2, etc.
            $attributes = $match[2];    // any attributes inside the header tag
            $headerContent = $match[3]; // the inner HTML/text of the header

            // Determine header level (1-6).
            preg_match('/h([1-6])/i', $tag, $levelMatch);
            $level = (int)$levelMatch[1];

            // Only process headers up to the maximum level.
            if ($level > $maxLevel)
            {
                continue;
            }

            // Update chapter counters if numbering is enabled.
            if ($chapterNumbers)
            {
                $chapterCount[$level] = isset($chapterCount[$level]) ? $chapterCount[$level] + 1 : 1;
                // Reset counters for deeper levels.
                for ($i = $level + 1; $i <= 6; $i++)
                {
                    $chapterCount[$i] = 0;
                }
                // Build numbering prefix for display.
                $numberParts = array();
                for ($i = 1; $i <= $level; $i++)
                {
                    if (isset($chapterCount[$i]) && $chapterCount[$i] > 0)
                    {
                        $numberParts[] = $chapterCount[$i];
                    }
                }
                $displayNumber = implode('.', $numberParts) . '. ';
                $numberSlug = rtrim(str_replace('.', '-', implode('.', $chapterCount)), '-');
            }
            else
            {
                $displayNumber = '';
            }

            // Generate a slug from the header text.
            $slug = $this->slugify($headerContent);

            // Build the anchor name.
            if ($chapterNumbers)
            {
                $anchorName = $numberSlug . '-' . $slug;
            }
            else
            {
                $anchorName = $slug;
            }

            // If chapter numbering is enabled, update the header text.
            $modifiedContent = $displayNumber . $headerContent;

            // Store header info for later use in TOC and for anchor insertion.
            $headers[] = array(
                'level'      => $level,
                'anchor'     => $anchorName,
                'title'      => $modifiedContent,
                'tag'        => $tag,
                'attributes' => $attributes,
                'fullTag'    => $match[0]
            );
        }

        // Build the nested TOC HTML.
        $tocHtml = $this->buildTOC($headers);

        // Replace the first {toc ...} tag with the generated TOC.
        $content = preg_replace('/\{toc(?:\s+.*)?\}/i', $tocHtml, $content, 1);

        // Insert anchor tags and update header tags in the content.
        foreach ($headers as $header)
        {
            // Create the anchor tag.
            $anchorTag = '<a name="' . $header['anchor'] . '"></a>';
            // Rebuild the header tag with the updated inner content.
            $newHeaderTag = '<' . $header['tag'] . $header['attributes'] . '>' . $header['title'] . '</' . $header['tag'] . '>';
            $replacement = $anchorTag . $newHeaderTag;
            // Replace only the first occurrence of this header.
            $content = preg_replace('/' . preg_quote($header['fullTag'], '/') . '/', $replacement, $content, 1);
        }

        // Update the article text.
        $article->text = $content;
    }

    /**
     * Parse parameter string into an associative array.
     *
     * The parameter string is expected in the format:
     *     param1="value1" param2=value2
     *
     * @param   string  $paramString  The parameter string from the {toc ...} tag.
     *
     * @return  array  Associative array of parameters.
     */
    protected function parseParams(string $paramString) : array
    {
        $params = array();
        // Match key=value pairs (value may be quoted).
        preg_match_all('/(\w+)\s*=\s*(["\']?)(.*?)\2(\s|$)/', $paramString, $matches, PREG_SET_ORDER);
        foreach ($matches as $match)
        {
            $params[$match[1]] = $match[3];
        }
        return $params;
    }

    /**
     * Convert a string into a URL-friendly slug.
     *
     * @param   string  $text  The text to be slugified.
     *
     * @return  string  The slugified text.
     */
    protected function slugify(string $text) : string
    {
        // Convert to lowercase.
        $text = strtolower($text);
        // Remove any HTML tags.
        $text = strip_tags($text);
        // Replace non-alphanumeric characters with hyphens.
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        // Trim hyphens from beginning and end.
        $text = trim($text, '-');
        return $text;
    }

    /**
     * Build a nested HTML list from header information.
     *
     * This method creates a nested unordered list (<ul>) where each list item (<li>)
     * contains a link to the corresponding header anchor.
     *
     * @param   array  $headers  Array of headers (each with level, anchor, and title).
     *
     * @return  string  The HTML for the table of contents.
     */
    protected function buildTOC(array $headers) : string
    {
        $html = "";
        $prevLevel = 0;

        foreach ($headers as $header)
        {
            $currentLevel = $header['level'];

            if ($currentLevel > $prevLevel)
            {
                for ($i = $prevLevel; $i < $currentLevel; $i++)
                {
                    $html .= "\n<ul>\n";
                }
            }
            else if ($currentLevel < $prevLevel)
            {
                for ($i = $currentLevel; $i < $prevLevel; $i++)
                {
                    $html .= "\n</li>\n</ul>\n";
                }
                $html .= "\n</li>\n";
            }
            else
            {
                if ($html !== "")
                {
                    $html .= "\n</li>\n";
                }
            }

            $html .= '<li><a href="#' . $header['anchor'] . '">' . $header['title'] . '</a>';
            $prevLevel = $currentLevel;
        }

        // Close any open lists.
        for ($i = 0; $i < $prevLevel; $i++)
        {
            $html .= "\n</li>\n</ul>\n";
        }

        return $html;
    }
}
