<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contacts = Contact::withCount('contactLists')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts
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
     * Get contacts data for select2
     */
    public function getContacts(Request $request)
    {
        $contactIds = $request->contact_ids;

        // Ensure $contactIds is an array
        if (is_string($contactIds)) {
            $contactIds = json_decode($contactIds, true);
        }

        if (!$contactIds || !is_array($contactIds) || empty($contactIds)) {
            return response()->json(['success' => false, 'message' => 'No contacts found.']);
        }

        $contacts = Contact::whereIn('contacts.id', $contactIds)
            ->leftJoin('contact_lists', 'contacts.id', '=', 'contact_lists.contact_id')
            ->select('contacts.id', 'contacts.title', DB::raw('COUNT(contact_lists.id) as contact_list_count'))
            ->groupBy('contacts.id', 'contacts.title')
            ->get();

        return response()->json([
            'success' => true,
            'contacts' => $contacts
        ]);
    }
}
