<?php

namespace App\Http\Repository\Task;

interface JobVacancyInterface
{
  public function createJobVacancy($validated);
  public function getJobVacancyById($id);
  public function getExportAllJobVacancyByEventId($id);
  public function getJobVacancyBySlug($slug);
  public function getJobVacancyWithCompanyBySlug($slug);
  public function updateJobVacancyById($validated, $id);
  public function deleteJobVacancyById($id);
  public function deleteJobVacancyBySlug($slug);
  public function getAllJobVacanciesAdmin();
  public function getAllEventJobVacanciesAdmin();
  public function getAllSpecialEventJobVacanciesAdmin();
  public function getAllSpecialEventJobVacanciesOperator($institution_id);
  public function getAllApplicationBySeeker($id, $query, $isOpen);
  public function getAllJobVacancyByCompanyId($id, $query, $isOpen, $isActive);
  public function getSimilarJobByPosition($position_name, $slug);
  public function getAllJobVacanciesByQuery($query, $category_id, $sub_category_id, $isIndex);
  public function countJobVacancies();
}
