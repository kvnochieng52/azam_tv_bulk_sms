<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        
        $query = Contact::withCount('contactLists');
        
        // Apply search filter if search term is provided
        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }
        
        $contacts = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Contacts/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'is_active' => 'required|integer',
            'contact_lists.*.name' => 'required|string|max:255',
            'contact_lists.*.telephone' => 'required|string|max:15',
        ]);

        $contact = Contact::create([
            'title' => $request->title,
            'is_active' => $request->is_active,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        foreach ($request->contact_lists as $list) {
            $contact->contactLists()->create([
                'name' => $list['name'],
                'telephone' => $list['telephone']
            ]);
        }

        return redirect()->route('contacts.index')->with('success', 'Contact created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact)
    {
        $contact->load('contactLists');
        
        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'contactLists' => $contact->contactLists
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact)
    {
        $contact->load('contactLists');
        
        return Inertia::render('Contacts/Edit', [
            'contact' => $contact,
            'contactLists' => $contact->contactLists
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'is_active' => 'required|integer',
            'contact_lists.*.name' => 'required|string|max:255',
            'contact_lists.*.telephone' => 'required|string|max:15',
        ]);

        $contact->update([
            'title' => $request->title,
            'is_active' => $request->is_active,
            'updated_by' => Auth::id(),
        ]);

        // Delete existing contact lists and re-create them
        $contact->contactLists()->delete();

        foreach ($request->contact_lists as $list) {
            $contact->contactLists()->create([
                'name' => $list['name'],
                'telephone' => $list['telephone']
            ]);
        }

        return redirect()->route('contacts.index')->with('success', 'Contact updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact)
    {
        $contact->contactLists()->delete();
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact deleted successfully.');
    }
    
    /**
     * Get contacts data based on contact IDs
     * Simple approach for contact groups with their counts
     */
    public function getContacts(Request $request)
    {
        // Get contact_ids parameter (could be in request body or query string)
        $contactIds = $request->input('contact_ids', $request->query('contact_ids'));
        
        // Handle various formats of the parameter
        if (is_string($contactIds)) {
            // Try to decode JSON string
            try {
                $contactIds = json_decode($contactIds, true);
            } catch (\Exception $e) {
                // If JSON decode fails, try comma-separated format
                $contactIds = array_map('trim', explode(',', $contactIds));
            }
        }
        
        // Ensure we have valid contact IDs
        if (!$contactIds || !is_array($contactIds) || empty($contactIds)) {
            return response()->json([
                'success' => false, 
                'message' => 'No valid contact IDs provided.'
            ]);
        }
        
        // Log for debugging
        Log::info('Getting contacts for IDs: ' . json_encode($contactIds));
        
        // Step 1: Get basic contact group information
        $contacts = Contact::whereIn('id', $contactIds)
            ->where('is_active', 1)
            ->select('id', 'title')
            ->get();
        
        // Step 2: For each contact group, get the count of contacts
        $contactGroups = [];
        
        foreach ($contacts as $contact) {
            // Count contact lists entries for this contact group
            $contactListsCount = DB::table('contact_lists')
                ->where('contact_id', $contact->id)
                ->where('is_active', 1)
                ->count();
            
            // Get contact lists for details
            $contactLists = DB::table('contact_lists')
                ->where('contact_id', $contact->id)
                ->where('is_active', 1)
                ->select('id', 'name', 'telephone')
                ->get();
            
            // Add to results array
            $contactGroups[] = [
                'id' => $contact->id,
                'full_name' => $contact->title,
                'contacts_count' => $contactListsCount,
                'lists' => $contactLists->map(function($list) {
                    return [
                        'id' => $list->id,
                        'name' => $list->name,
                        'telephone' => $list->telephone
                    ];
                })
            ];
        }

        return response()->json([
            'success' => true,
            'contacts' => $contactGroups
        ]);
    }
}
