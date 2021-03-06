<?php namespace Project;

class Issue extends \Eloquent {

   public static $table = 'projects_issues';
   public static $timestamps = true;

   /******************************************************************
    * Methods to use against loaded project
    ******************************************************************/

	/**
	 * @return User
	 */
	public function user()
	{
		return $this->belongs_to('\User', 'created_by');
	}

   /**
    * @return User
    */
   public function assigned()
   {
      return $this->belongs_to('\User', 'assigned_to');
   }

	/**
	 * @return User
	 */
	public function updated()
	{
		return $this->belongs_to('\User', 'updated_by');
	}

   /**
    * @return User
    */
   public function closer()
   {
      return $this->belongs_to('\User', 'closed_by');
   }

   public function comments()
   {
      return $this->has_many('Project\Issue\Comment', 'issue_id')
				->order_by('created_at', 'ASC');
   }

	public function attachments()
	{
		return $this->has_many('Project\Issue\Attachment', 'issue_id')->where('comment_id', '=', 0);
	}

   /**
    * Generate a URL for the active project
    *
    * @param  string  $url
    * @return string
    */
   public function to($url = '')
   {
      return \URL::to('project/' . $this->project_id . '/issue/' . $this->id . (($url) ? '/'. $url : ''));
   }

   /**
    * Reassign the issue to a new user
    *
    * @param  int  $user_id
    * @return void
    */
   public function reassign($user_id)
   {
      $this->assigned_to = $user_id;
      $this->save();

      \User\Activity::add(5, $this->project_id, $this->id, $user_id);
   }

   /**
    * Change the status of an issue
    *
    * @param  int   $status
    * @return void
    */
   public function change_status($status)
   {
      if($status == 0)
      {
         $this->closed_by = \Auth::user()->id;
         $this->closed_at = \DB::raw('NOW()');

         /* Add to activity log */
         \User\Activity::add(3, $this->project_id, $this->id);
      }
      else
      {
         /* Add to activity Log */
         \User\Activity::add(4, $this->project_id, $this->id);
      }

      $this->status = $status;
      $this->save();
   }

   /******************************************************************
  	 * Static methods for working with issues
  	 ******************************************************************/

   /**
    * Current loaded Issue
    *
    * @var Issue
    */
   private static $current = null;

   /**
    * Return the current loaded Issue
    *
    * @return Issue
    */
   public static function current()
   {
      return static::$current;
   }

   /**
    * Load a new Issue into $current, based on the $id
    *
    * @param   int   $id
    * @return  Issue
    */
   public static function load_issue($id)
   {
      static::$current = static::find($id);

      return static::$current;
   }

   /**
    * Create a new issue
    *
    * @param  array    $input
    * @param  \Project  $project
    * @return Issue
    */
   public static function create_issue($input, $project)
   {
		$rules = array(
			'title' => 'required|max:200',
			'body' => 'required'
		);

		$validator = \Validator::make($input, $rules);

		if($validator->invalid())
		{
			return array(
				'success' => false,
				'errors' => $validator
			);
		}

      $fill = array(
         'created_by' => \Auth::user()->id,
         'project_id' => $project->id,
         'title' => $input['title'],
         'body' => $input['body']
      );

		if(\Auth::user()->permission('issue-modify'))
		{
			$fill['assigned_to'] = $input['assigned_to'];
		}

      $issue = new static;
      $issue->fill($fill);
      $issue->save();

      /* Add to user's activity log */
      \User\Activity::add(1, $project->id, $issue->id);

		/* Add attachments to issue */
		$query = '
			UPDATE `projects_issues_attachments`
			SET issue_id = ?
			WHERE upload_token = ? AND uploaded_by = ?';

		\DB::query($query, array($issue->id, $input['token'], \Auth::user()->id));

		/* Return success and issue object */
		return array(
			'success' => true,
			'issue' => $issue
		);
   }

	public static function count_issues()
	{
		/* Count Open Issues */
		$sql = '
		SELECT COUNT(i.id) AS total
		FROM projects_issues i
		JOIN projects p ON p.id = i.project_id
		WHERE p.status = 1 AND i.status = 1
		GROUP BY i.id
		';

		$count = \DB::first($sql);
		$open_issues = !$count ? 0 : $count->total;

		/* Count Closed Issues - If the project is closed, so is the issue */
		$sql = '
		SELECT COUNT(i.id) AS total
		FROM projects_issues i
		JOIN projects p ON p.id = i.project_id
		WHERE p.status = 1 AND i.status = 0
		GROUP BY i.id
		';

		$count = (int) \DB::first($sql);
		$closed_issues_open_project = !$count ? 0 : $count->total;

		$sql = '
		SELECT COUNT(i.id) AS total
		FROM projects_issues i
		JOIN projects p ON p.id = i.project_id
		WHERE p.status = 0
		GROUP BY i.id
		';

		$count = (int) \DB::first($sql);
		$issues_closed_project = !$count ? 0 : $count->total;

		$closed_issues = ($closed_issues_open_project + $issues_closed_project);

		return array(
			'open' => $open_issues,
			'closed' => $closed_issues
		);
	}

}