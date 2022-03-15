<?php

namespace App\Http\Repository\Task;


interface JobSeekerInterface {
  public function createJobSeeker($validated);
  public function getJobSeekerById($id);
  public function getJobSeekerBySlug($slug);
  public function getJobSeekerByNIKs($NIKs);
  public function getJobSeekerByNotNIKs($NIKs);
  public function getAllJobSeekerWithUser($province_id = null, $city_id = null, $district_id = null, $sub_district_id = null, $download = false, $isAdmin = true);
  public function updateJobSeekerById($validated, $id);
  public function deleteJobSeekerById($id);
  public function getJobSeekerWithAllRelation($id);
  public function getJobSeekerRecomendations($position_name, $category_id, $institution_id = null);
  public function getSeekersByJobId($id, $query, $status);
  public function getSeekerByJobIdAdmin($job_vacancy_id);
  public function getPartisipantClientByEventCode($event_id, $forExport, $isGroup, $isRegister = false);
  public function isCompleted($job_seeker_id);
  public function countJobSeekers();
  public function getAllSeekerGroupStatus($year);
}