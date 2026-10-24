<?php
namespace App\Models;
use App\Models\Concerns\SerializesDatesAsPolr2;
use Illuminate\Database\Eloquent\Model;


class User extends Model {
    use SerializesDatesAsPolr2;

    protected $table = 'users';
}
