<?php

namespace Database\Seeders;

use App\Models\Helper;
use App\Models\User;
use Illuminate\Database\Seeder;
use Bouncer;

class BouncerSedder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*******************************************************************************
        *                                Copyright : AGmedia                           *
        *                              email: filip@agmedia.hr                         *
        *******************************************************************************/
        //
        // ROLES
        //
        $superadmin = Bouncer::role()->firstOrCreate([
            'name' => 'master',
            'title' => 'The Master of the Universe',
        ]);

        $admin = Bouncer::role()->firstOrCreate([
            'name' => 'admin',
            'title' => 'Administrator',
        ]);

        $editor = Bouncer::role()->firstOrCreate([
            'name' => 'editor',
            'title' => 'Editor',
        ]);

        $customer = Bouncer::role()->firstOrCreate([
            'name' => 'customer',
            'title' => 'Customer',
        ]);

        /*******************************************************************************
        *                                Copyright : AGmedia                           *
        *                              email: filip@agmedia.hr                         *
        *******************************************************************************/
        //
        // ABILITIES
        //
        $view_products = Bouncer::ability()->firstOrCreate([
            'name' => 'view-products',
            'title' => 'Pregled svih proizvoda'
        ]);

        $create_products = Bouncer::ability()->firstOrCreate([
            'name' => 'create-products',
            'title' => 'Izrada novih proizvoda'
        ]);

        $edit_products = Bouncer::ability()->firstOrCreate([
            'name' => 'edit-products',
            'title' => 'Uređivanje svih proizvoda'
        ]);

        $delete_products = Bouncer::ability()->firstOrCreate([
            'name' => 'delete-products',
            'title' => 'Brisanje proizvoda'
        ]);


        /*******************************************************************************
         *                                Copyright : AGmedia                           *
         *                              email: filip@agmedia.hr                         *
         *******************************************************************************/
        //
        // PERMISSIONS
        //
        Bouncer::allow($superadmin)->everything();
        //
        Bouncer::allow($admin)->to($view_products);
        Bouncer::allow($admin)->to($create_products);
        Bouncer::allow($admin)->to($edit_products);
        Bouncer::allow($admin)->to($delete_products);

        Bouncer::allow($editor)->to($view_products);
        Bouncer::allow($editor)->to($create_products);
        Bouncer::allow($editor)->to($edit_products);

        /**
         * Custom
         */
        $users = User::whereIn('id', [1, 2])->get();

        foreach ($users as $user) {
            $user->assign('master');
        }


        /*******************************************************************************
        *                                Copyright : AGmedia                           *
        *                              email: filip@agmedia.hr                         *
        *******************************************************************************/



    }
}
