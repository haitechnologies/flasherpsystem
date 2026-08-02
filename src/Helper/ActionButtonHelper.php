<?php

declare(strict_types=1);

namespace App\Helper;

/**
 * ActionButtonHelper - Centralized Action Button Rendering
 *
 * Standardizes action button HTML and styling across all DataTable handlers.
 * Ensures consistent icons, spacing, tooltips, and permissions.
 *
 * @package Helpers
 */
class ActionButtonHelper
{
    /**
     * Normalize URL for local/prod environments.
     * - Absolute URLs stay untouched.
     * - Relative/root paths are prefixed with configured base URL.
     */
    private static function normalizePublicUrl($url)
    {
        $url = (string)$url;
        if ($url === '') {
            return '#';
        }

        // Keep absolute/protocol-relative URLs as-is.
        if (preg_match('#^(https?:)?//#i', $url)) {
            return $url;
        }

        $baseUrl = rtrim((string)($_ENV['APP_URL'] ?? $GLOBALS['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return $url;
        }

        return $baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * View button - opens modal or preview
     * Icon: ph-eye (blue)
     *
     * @param int $id Record ID
     * @param string $module Module name
     * @return string HTML action button
     */
    public static function viewButton($id, $module)
    {
        return '<a href="#" ' .
            'data-action="view_' . htmlspecialchars($module) . '" ' .
            'data-id="' . (int)$id . '" ' .
            'title="View" ' .
            'class="action-btn action-view"><i class="ph-eye"></i></a>';
    }

    /**
     * Edit button - opens edit form
     * Icon: ph-pencil (green)
     *
     * @param int $id Record ID
     * @param string $editPage Form page URL (e.g. 'email_templates.php')
     * @param string $module Module name
     * @param string $label Label
     * @param bool $showText Show text label
     * @return string HTML action button
     */
    public static function editButton($id, $editPage, $module, $label = 'Edit', $showText = true)
    {
        $text = $showText ? ' <span class="d-none d-md-inline">' . htmlspecialchars($label) . '</span>' : '';
        return '<a href="' . htmlspecialchars($editPage) .
            '?action=edit_' . htmlspecialchars($module) .
            '&id=' . (int)$id . '" ' .
            'title="' . htmlspecialchars($label) . '" ' .
            'class="action-btn action-edit"><i class="ph-pencil"></i>' . $text . '</a>';
    }

    /**
     * Delete button - triggers delete confirmation
     * Icon: ph-trash (red)
     *
     * @param int $id Record ID
     * @param string $module Module name
     * @return string HTML action button
     */
    public static function deleteButton($id, $module, $confirmMessage = '')
    {
        $confirmAttr = $confirmMessage !== '' ? ' data-confirm="' . htmlspecialchars($confirmMessage) . '" ' : '';
        return '<a href="#" ' .
            'data-action="delete_record" ' .
            'data-module="' . htmlspecialchars($module) . '" ' .
            'data-id="' . (int)$id . '" ' .
            $confirmAttr .
            'title="Delete" ' .
            'class="action-btn action-delete"><i class="ph-trash"></i></a>';
    }

    /**
     * Publish/Unpublish toggle button
     * Icon: ph-eye (publish) / ph-eye-slash (unpublish) - cyan
     *
     * @param int $id Record ID
     * @param string $module Module name
     * @param bool $isPublished Current publish state
     * @return string HTML action button
     */
    public static function publishButton($id, $module, $isPublished)
    {
        $icon = $isPublished ? 'ph-eye-slash' : 'ph-eye';
        $title = $isPublished ? 'Unpublish' : 'Publish';

        return '<a href="#" ' .
            'data-action="toggle_publish" ' .
            'data-id="' . (int)$id . '" ' .
            'data-module="' . htmlspecialchars($module) . '" ' .
            'title="' . $title . '" ' .
            'class="action-btn action-publish"><i class="' . $icon . '"></i></a>';
    }

    /**
     * Download button - downloads file or export
     * Icon: ph-download (gray)
     *
     * @param int $id Record ID
     * @param string $downloadUrl Download URL
     * @return string HTML action button
     */
    public static function downloadButton($id, $downloadUrl)
    {
        return '<a href="' . htmlspecialchars($downloadUrl) . '" ' .
            'title="Download" ' .
            'class="action-btn action-download"><i class="ph-download"></i></a>';
    }

    /**
     * Test button - triggers test/send action (e.g., test email)
     * Icon: ph-paper-plane-tilt (orange)
     *
     * @param int $id Record ID
     * @param string $function JavaScript function name (e.g., 'openTestEmailModal')
     * @param string $title Button tooltip text
     * @return string HTML action button
     */
    public static function testButton($id, $function, $title = 'Send Test')
    {
        return '<a href="javascript:void(0)" ' .
            'onclick="' . htmlspecialchars($function) . '(' . (int)$id . ')" ' .
            'title="' . htmlspecialchars($title) . '" ' .
            'class="action-btn action-test"><i class="ph-paper-plane-tilt"></i></a>';
    }

    /**
     * Public page button (frontend URL)
     * Icon: ph-globe
     *
     * @param string $url Public URL (relative or absolute)
     * @param string $title Tooltip text
     * @return string HTML action button
     */
    public static function publicLinkButton($url, $title = 'View Public Page')
    {
        $normalizedUrl = self::normalizePublicUrl($url);
        return '<a href="' . htmlspecialchars($normalizedUrl) . '" ' .
            'target="_blank" rel="noopener noreferrer" ' .
            'title="' . htmlspecialchars($title) . '" ' .
            'class="action-btn action-public"><i class="ph-globe"></i></a>';
    }

    /**
     * AMP page button (frontend AMP URL)
     * Icon: ph-device-mobile
     *
     * @param string $url AMP URL (relative or absolute)
     * @param string $title Tooltip text
     * @return string HTML action button
     */
    public static function ampLinkButton($url, $title = 'View AMP Page')
    {
        $normalizedUrl = self::normalizePublicUrl($url);
        return '<a href="' . htmlspecialchars($normalizedUrl) . '" ' .
            'target="_blank" rel="noopener noreferrer" ' .
            'title="' . htmlspecialchars($title) . '" ' .
            'class="action-btn action-public"><i class="ph-device-mobile"></i></a>';
    }

    /**
     * Group multiple action buttons with proper spacing
     * Wraps buttons in a container for alignment
     *
     * @param string ...$buttons Variable number of button HTML strings
     * @return string HTML button group container
     */
    public static function group(...$buttons)
    {
        $html = '<div class="action-buttons">';
        foreach ($buttons as $button) {
            if (!empty($button)) {
                $html .= $button . ' ';
            }
        }
        $html .= '</div>';
        return $html;
    }
}
