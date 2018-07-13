<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Http\Request;

class UserAddressesController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->address;
        return view('user_address.index', compact('addresses'));
    }

    public function create()
    {
        return view('user_address.create_and_edit', ['address' => new UserAddress()]);
    }
}
