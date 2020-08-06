<?php

use App\Role;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRoleUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unsigned();
            $table->unsignedBigInteger('role_id')->unsigned();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });

        $adminRole = Role::create([
            'name' => 'admin'
        ]);
        $normalRole = Role::create([
            'name' => 'normal'
        ]);
        $adminPassword = env('ADMIN_PASSWORD', '');
        if ($adminPassword) {
            $adminUser = User::create([
                'name' => 'Admin',
                'phone' => '+989388855548',
                'email' => 'siavash.hekmatnia@gmail.com',
                'country' => 'Iran',
                'currency' => 'IRR',
                'password' => Hash::make($adminPassword),
                'referral_code' => 'ADMIN'
            ]);
            $roleIds = [$adminRole->id, $normalRole->id];
            $adminUser->roles()->sync($roleIds);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('role_user');
    }
}
