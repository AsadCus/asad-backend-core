<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Delete a single record, or every id in the request's `ids` array if present.
     * Shared by master CRUD controllers whose destroy() supports both single and bulk delete.
     */
    protected function deleteOneOrMany(Request $request, string $id, callable $delete): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $itemId) {
                $delete($itemId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
