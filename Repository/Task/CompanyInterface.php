<?php

namespace App\Http\Repository\Task;

interface CompanyInterface
{
  public function getCompanyById($id);
  public function getCompanyBySlug($slug);
  public function updateCompanyById($validated, $id);
  public function deleteCompanyById($id);
  public function getAllCompanies($isEditEvent, $event_id);
  public function getPartisipantCompanyByEventCode($event_code, $forExport);
  public function getAllCompanyByQuery($query, $province_id, $city_id);
  public function getCompanyWithJobVacanciesBySlug($slug);
  public function countCompanies();
}
