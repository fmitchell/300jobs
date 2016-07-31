<?php

use App\Jobs;
use Illuminate\Database\Seeder;

class JobsTableSeeder extends Seeder
{
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run()
  {
    DB::table('jobs')->delete();
    $json = File::get("database/data/jobs.json");
    $data = json_decode($json);
    foreach ($data as $obj) {
      Jobs::create(
        array(
          'title' => $obj->title,
          'date' => $obj->date,
          'onet' => $obj->onet,
          'link' => $obj->link,
          'location' => $obj->location,
          'company' => $obj->company
        )
      );
    }
  }
}
