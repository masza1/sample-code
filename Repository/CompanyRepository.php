<?php

namespace App\Http\Repository;

use App\Http\Repository\Task\CompanyInterface;
use App\Models\Company;
use App\Models\Event;

class CompanyRepository implements CompanyInterface
{
  protected $model;

  public function __construct(Company $Company)
  {
    $this->model = $Company;
  }

  public function activateEvent($event_code, $id)
  {
    $company = $this->model->find($id);
    if($event = Event::where('event_code', $event_code)->first()){
      // if($event->isSpecialEvent && !$company->events()->where('event_code', $event_code)->first()){
      //   return 400;
      // }
      if($event->status == 'publish'){
        if ($company->events()->where('event_code', $event_code)->first()) {
          if ($company->events()->updateExistingPivot($event, ['status' => 'active'])) {
            return 200;
          }
          return 201;
        }else if($company->events()->syncWithPivotValues($event->id,['status' => 'active'], false)) {
          return 200;
        }
      }
    }
    return 400;
  }

  public function getCompanyById($id)
  {
    return $this->model->find($id);
  }

  public function getCompanyBySlug($slug)
  {
    return $this->model->where('slug', $slug)->first();
  }

  public function updateCompanyById($validated, $id)
  {
    $company = $this->model->find($id);
    if ($company->logo_company != null || $validated['logo_company'] != null) {
      $validated['isCompleted'] = 1;
    } else {
      $validated['isCompleted'] = 0;
    }

    if ($validated['logo_company'] != null) {
      if (file_exists(public_path('storage/' . $company->logo_company) && $company->logo_company != null)) {
        unlink(public_path('storage/' . $company->logo_company));
      }
    } else {
      unset($validated['logo_company']);
    }
    return $company->update($validated);
  }

  public function deleteCompanyById($id)
  {
    $company = $this->model->find($id);
    if (file_exists(public_path('storage/' . $company->logo_company)) && $company->logo_company != null) {
      unlink(public_path('storage/' . $company->logo_company));
    }
    return $company->delete();
  }

  public function getAllCompanies($isEditEvent, $event_id)
  {
    return $this->model->with('user')->select('companies.id', 'companies.slug', 'user_id', 'company_name', 'nama_HRD', 'phone_number', 'business_field', 'KTP_AN')
      ->when($isEditEvent, function ($q) use ($event_id) {
        $q->with(['events' => function ($qr) use ($event_id) {
          $qr->select('companies_events.id as ce_id', 'companies_events.company_id')->where('companies_events.event_id', $event_id);
        }]);
      })
      ->orderBy('company_name', 'ASC')->get();
  }

  public function getPartisipantCompanyByEventCode($event_code, $forExport)
  {
    return $this->model->withOnly(['user:id,email', 'events' => function($q){
      $q->select('events.id')->without(['province', 'city', 'institution']);
    }])->whereHas('events', function($q) use($event_code){
      $q->where('companies_events.event_id', $event_code);
    })->when($forExport, function($q){
      $q->select('id', 'user_id', 'company_name', 'business_field', 'address','phone_number', 'nama_HRD');
    })->when(!$forExport, function($q){
      $q->select('id', 'user_id','company_name', 'phone_number', 'nama_HRD');
    })->orderBy('company_name', 'ASC')->get();
  }

  public function getCompanyWithJobVacanciesBySlug($slug)
  {
    return $this->model->with(['job_vacancies' => function ($q) {
      return $q->where('isActive', 1)->where('open_until', '>=', date('Y-m-d'))->where('event_id', null);
    }])->where('slug', $slug)->first();
  }

  public function getAllCompanyByQuery($query, $province_id, $city_id)
  {
    return $this->model->with(['job_vacancies' => function ($q) {
      return $q->where('isActive', 1)->where('open_until', '>=', date('Y-m-d'))->where('event_id', null);
    }])->when($query != null, function ($q) use ($query) {
      $q->where('company_name', 'LIKE', '%' . $query . '%');
    })->when($province_id != null, function ($q) use ($province_id) {
      $q->where('province_id', $province_id);
    })->when($city_id != null, function ($q) use ($city_id) {
      $q->where('city_id', $city_id);
    })->where('isCompleted', 1)->paginate(10);
  }

  public function getCompanyWithJobEventById($id)
  {
    return $this->model->select('id')->withCount(['job_vacancies as jobs_count', 'job_vacancies as event_count' => function ($q) {
      $q->where('event_id', '!=',null)->whereHas('event', function ($qr) {
        $qr->where('institution_id', null);
      });
    }, 'job_vacancies as special_event_count' => function($q){
      $q->where('event_id', '!=', null)->whereHas('event', function ($qr) {
        $qr->where('institution_id', '!=', null);
      });
    }])->with(['job_vacancies' => function ($q) {
      $q->select('job_vacancies.id','company_id', 'job_vacancies.slug','position_name', 'job_vacancies.updated_at', 'open_until')
      ->withCount('applicants')->orderby('updated_at')->where('open_until', '>=', date('Y-m-d'));
    }])->where('id', $id)->first();
  }

  public function countCompanies(){
    return $this->model->count();
  }
}
