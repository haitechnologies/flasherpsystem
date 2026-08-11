<?php

/**
 * Geo States DataTable Handler
 *
 * Server-side DataTables processing for hai_geo_states table
 * Returns 9 columns: id, slug, state, state_ar, country_id, is_active, created_at, actions
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class GeoStatesDataTable extends BaseDataTable
{
    protected $table = DB::GEO_STATES;

    protected function getColumns()
    {
        return ['id', 'slug', 'state', 'state_ar', 'country_id', 'is_active', 'created_at'];
    }

    protected function getDefaultOrder()
    {
        return 'id DESC';
    }

    protected function formatRow($row, $requestData = [])
    {
        return [
            'id' => $row['id'],
            'slug' => htmlspecialchars($row['slug']),
            'state' => htmlspecialchars($row['state']),
            'state_ar' => htmlspecialchars($row['state_ar'] ?? ''),
            'country_id' => $row['country_id'],
            'is_active' => $row['is_active'] ? '<span class="badge bg-success bg-opacity-20 text-success">Active</span>' : '<span class="badge bg-danger bg-opacity-20 text-danger">Inactive</span>',
            'created_at' => !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '-',
            'actions' => $this->getActionButtons($row['id'], 'geo_states')
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $buttons = [];
        if (class_exists('ActionButtonHelper')) {
            $buttons[] = ActionButtonHelper::editButton($id, $module . '.php', $module, 'Edit', false);
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
