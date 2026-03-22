<?php

namespace App\Http\Controllers;

use App\Models\ExpenseEntry;
use App\Http\Requests\StoreExpenseEntryRequest;
use App\Http\Requests\UpdateExpenseEntryRequest;
use Illuminate\Http\Request;

class ExpenseEntryController extends Controller
{
    public function store(StoreExpenseEntryRequest $request)
    {
        $entry = ExpenseEntry::create($request->validated());

        return response()->json($entry->load('category', 'creator', 'updater'), 201);
    }

    public function update(UpdateExpenseEntryRequest $request, ExpenseEntry $expenseEntry)
    {
        $expenseEntry->update($request->validated());

        return response()->json($expenseEntry->load('category', 'creator', 'updater'));
    }

    public function destroy(ExpenseEntry $expenseEntry)
    {
        $this->authorize('delete', $expenseEntry);
        $expenseEntry->delete();

        return response()->json(['deleted' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:expense_entries,id'],
        ]);

        foreach ($request->order as $position => $id) {
            ExpenseEntry::where('id', $id)->update(['sort_order' => $position]);
        }

        return response()->json(['reordered' => true]);
    }
}
