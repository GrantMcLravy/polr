<?php
namespace App\Models;
use App\Models\Concerns\SerializesDatesAsPolr2;
use Illuminate\Database\Eloquent\Model;

class Click extends Model {
    use SerializesDatesAsPolr2;

    protected $table = 'clicks';
}
