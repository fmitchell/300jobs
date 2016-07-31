<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Jobs;
use App\Http\Requests;
use App\Http\Session;
use App\Http\Controllers\Controller;
use Services_Twilio_Twiml;

class JobsController extends Controller
{
  /**
   * Do search.
   *
   * @param \Illuminate\Http\Request $request
   * @return mixed
   */
  public function search(Request $request)
  {
    $body = $request->input('Body');
    if ($this->isChoiceAnswer($body, $request)) {
      return $this->selectedJob($body, $request);
    }

    $query = Jobs::where('title', 'LIKE', '%' . $body . '%');
    $count = $query->count();
    if ($count == 1) {
      return $this->singleResult($query);
    } elseif ($count > 1) {
      return $this->multipleResults($query, $request);
    } else {
      return $this->notFound();
    }
  }

  /**
   * Grab single job from body based on link.
   *
   * @param $body
   * @param $request
   * @return mixed
   */
  private function selectedJob($body, $request)
  {
    $link = $request->session()->get('jobs')->get($body - 1);
    return $this->singleResult(Jobs::where('link', $link));
  }

  /**
   * Check if we have a numeric choice made from options.
   *
   * Grab from session.
   *
   * @param $body
   * @param $request
   * @return bool
   */
  private function isChoiceAnswer($body, $request)
  {
    return is_numeric($body)
    && in_array(
      intval($body),
      range(1, $request->session()->get("jobs")->count())
    );
  }

  /**
   * Get single result.
   *
   * @param $query
   * @return mixed
   */
  private function singleResult($query)
  {
    $twiml = new Services_Twilio_Twiml;
    $job = $query->first();
    $twiml
      ->message(
      collect(
        [
          $job->title,
          $job->company,
          $job->location,
          $job->link
        ]
      )
        ->implode("\n")
    );
    return $this->xmlResponse($twiml);
  }

  /**
   * Multiple results.
   *
   * @param $query
   * @param \Illuminate\Http\Request $request
   * @return mixed
   */
  private function multipleResults($query, Request $request)
  {
    // Setup Twilio object.
    $twiml = new Services_Twilio_Twiml;

    // Get the jobs from the query.
    $jobs = $query->get();

    // Setup the session to store job link.
    $request->session()->put(
      'jobs', $jobs->map(
      function ($jobs, $key) {
        return $jobs->link;
      }
    )
    );

    // Map number keys to results
    $jobsMessage = $jobs->map(
      function ($job, $key) {
        $option = $key + 1;
        return "**($option) for $job->title at $job->company**";
      }
    );

    // Craft message to send back.
    $twiml->message(
      collect(
        ['We found multiple jobs, reply with:',
          $jobsMessage, 'Or start over with another search']
      )
        ->flatten()
        ->implode("\n")
    );

    // Return XML response
    return $this->xmlResponse($twiml);
  }

  /**
   * Not found response.
   *
   * @return mixed
   */
  private function notFound()
  {
    $twiml = new Services_Twilio_Twiml;
    $twiml->message('We did not find any jobs that match what you\'re looking for.');
    return $this->xmlResponse($twiml);
  }

  /**
   * Format XML response.
   *
   * @param $twiml
   * @return mixed
   */
  private function xmlResponse($twiml)
  {
    return response($twiml, 200)->header('Content-Type', 'application/xml');
  }
}
