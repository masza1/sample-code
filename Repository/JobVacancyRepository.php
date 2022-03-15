<?php

namespace App\Http\Repository;

use App\Http\Repository\Task\JobVacancyInterface;
use App\Models\JobVacancy;

class JobVacancyRepository implements JobVacancyInterface
{
  protected $model;

  public function __construct(JobVacancy $JobVacancy)
  {
    $this->model = $JobVacancy;
  }

  public function createJobVacancy($validated)
  {
    $master_requirement_id = $validated['master_requirement_id'];
    unset($validated['master_requirement_id']);
    if(($job_vacancy = $this->model->create($validated))){
      if(gettype($master_requirement_id) == 'array' && $master_requirement_id != null){
        if($job_vacancy->requirements()->sync($master_requirement_id)){
          return 200;
        }
      }
      return 201;
    }
    return 400;
  }

  public function getJobVacancyById($id)
  {
    return $this->model->with('requirements')->find($id);
  }

  public function getExportAllJobVacancyByEventId($id)
  {
    return $this->model->with(['applicants' => function ($q){
      $q->withOnly(['educationals' => function ($qr) {
        $qr->max('educational_type_id');
      }])->whereHas('educationals', function ($q) {
        $q->withOnly('educational_type');
      })
      ->select('job_seekers.id', 'user_id', 'fullname', 'NIK', 'phone_number', 'address', 'place_of_birth', 'birthdate', 'gender', 'head_professional');
    }])->whereHas('applicants')->where('event_id', $id)->select('job_vacancies.id', 'position_name','event_id')->orderby('position_name', 'DESC')->get();
  }

  public function getJobVacancyBySlug($slug)
  {
    return $this->model->with('requirements')->where('slug', $slug)->first();
  }

  public function getJobVacancyWithCompanyBySlug($slug)
  {
    return $this->model->with('company', 'event')->where('slug', $slug)->first();
  }

  public function updateJobVacancyById($validated, $id)
  {
    $job_vacancy = $this->model->where('id', $id)->first();
    $master_requirement_id = $validated['master_requirement_id'];
    unset($validated['master_requirement_id']);
    if ($validated['brosur'] != null) {
      if (file_exists(public_path('storage/' . $job_vacancy->brosur) && $job_vacancy->brosur != null)) {
        unlink(public_path('storage/' . $job_vacancy->brosur));
      }
    } else {
      unset($validated['brosur']);
    }
    if($job_vacancy->update($validated)){
      if (gettype($master_requirement_id) == 'array' && $master_requirement_id != null) {
        if ($job_vacancy->requirements()->sync($master_requirement_id)) {
          return 200;
        }
      }
      return 201;
    }
    return 400;
  }

  public function deleteJobVacancyById($id)
  {
    $job_vacancy = $this->model->where('id', $id)->first();
    if (file_exists(public_path('storage/' . $job_vacancy->brosur) && $job_vacancy->brosur != null)) {
      unlink(public_path('storage/' . $job_vacancy->brosur));
    }
    $job_vacancy->requirements()->sync([]);
    return $job_vacancy->delete();
  }

  public function deleteJobVacancyBySlug($slug)
  {
    $job_vacancy = $this->model->where('slug', $slug)->first();
    if (file_exists(public_path('storage/' . $job_vacancy->brosur) && $job_vacancy->brosur != null)) {
      unlink(public_path('storage/' . $job_vacancy->brosur));
    }
    $job_vacancy->requirements()->sync([]);
    return $job_vacancy->delete();
  }

  public function getAllJobVacancyByCompanyId($id, $query, $isOpen, $isActive)
  {
    return $this->model->where('company_id', $id)
      ->when($query != null, function ($qr) use ($query) {
        $qr->where('position_name', 'LIKE', '%' . $query . '%');
      })->when($isOpen != null, function ($qr) use ($isOpen) {
        if ($isOpen == 'active') {
          $qr->where('open_until', '>=', date('Y-m-d'));
        } else if ($isOpen == 'expired') {
          $qr->where('open_until', '<=', date('Y-m-d'));
        }
      })->when($isActive != null, function ($qr) use ($isActive) {
        if ($isActive == 'publish') {
          $qr->where('isActive', 1);
        } else if ($isActive == 'pending') {
          $qr->where('isActive', 0);
        }
      })->withCount('applicants')->where('event_id', null)->orderby('updated_at', 'DESC')->paginate(10);
  }
  public function getAllJobVacancyByEventId($id, $event_id, $query, $isOpen, $isActive)
  {
    return $this->model->where('company_id', $id)
      ->when($query != null, function ($qr) use ($query) {
        $qr->where('position_name', 'LIKE', '%' . $query . '%');
      })->when($isOpen != null, function ($qr) use ($isOpen) {
        if ($isOpen == 'active') {
          $qr->where('open_until', '>=', date('Y-m-d'));
        } else if ($isOpen == 'expired') {
          $qr->where('open_until', '<=', date('Y-m-d'));
        }
      })->when($isActive != null, function ($qr) use ($isActive) {
        if ($isActive == 'publish') {
          $qr->where('isActive', 1);
        } else if ($isActive == 'pending') {
          $qr->where('isActive', 0);
        }
      })->withCount('applicants')->where('event_id', $event_id)->orderby('updated_at', 'DESC')->paginate(10);
  }

  public function getJobVacanciesByEventQuery($event_id, $query, $category_id, $sub_category_id)
  {
    return $this->model->when($query != null, function ($qr) use ($query) {
      $qr->where('position_name', 'LIKE', '%' . $query . '%');
    })->when($category_id != null, function ($q) use ($category_id) {
      $q->where('category_id', $category_id);
    })->when($sub_category_id != null, function ($q) use ($sub_category_id) {
      $q->where('sub_category_id', $sub_category_id);
    })->where('isActive', 1)->where('event_id', $event_id)->orderby('updated_at', 'DESC')->paginate(10);
  }

  public function getAllApplicationBySeeker($id, $query, $isOpen)
  {
    return $this->model->with(['applicants' => function ($q) use ($id) {
      $q->select('applicants.id', 'applicants.status', 'applicants.created_at as application_time')
        ->where('job_seeker_id', $id);
    }, 'company' => function ($q) {
      $q->select('id', 'logo_company', 'company_name');
    }])->whereHas('applicants', function ($q) use ($id) {
      $q->where('job_seeker_id', $id);
    })->when($query != null, function ($qr) use ($query) {
      $qr->where('position_name', 'LIKE', '%' . $query . '%');
    })->when($isOpen != null, function ($qr) use ($isOpen) {
      if ($isOpen == 'active') {
        $qr->where('open_until', '>=', date('Y-m-d'));
      } else if ($isOpen == 'expired') {
        $qr->where('open_until', '<=', date('Y-m-d'));
      }
    })->orderby('updated_at', 'DESC')->paginate(10);
  }

  public function getSimilarJobByPosition($position_name, $slug)
  {
    $explode = explode(' ', $position_name);
    return $this->model->where(function ($q) use ($explode) {
      $q->where('position_name', 'LIKE', '%' . $explode[0] . '%');
      for ($i = 1; $i < count($explode); $i++) {
        $q->orwhere('position_name', 'LIKE', '%' . $explode[$i] . '%');
      }
    })->where('isActive', 1)->where('open_until', '>=', date('Y-m-d'))->where('event_id', null)
      ->where('slug', '!=', $slug)->distinct()->orderBy('updated_at', 'DESC')->limit(5)->get();
  }

  public function getAllJobVacanciesByQuery($query, $category_id, $sub_category_id, $isIndex)
  {
    $job_vacancies =  $this->model->with('company')->when($query != null, function ($q) use ($query) {
      $q->where('position_name', 'LIKE', '%' . $query . '%');
    })->when($category_id != null, function ($q) use ($category_id) {
      $q->where('category_id', $category_id);
    })->when($sub_category_id != null, function ($q) use ($sub_category_id) {
      $q->where('sub_category_id', $sub_category_id);
    })->where('isActive', 1)->where('open_until', '>=', date('Y-m-d'))->where('event_id', null);

    if ($isIndex) {
      return $job_vacancies->orderBy('updated_at', 'DESC')->limit(8)->get();
    } else {
      return $job_vacancies->orderBy('updated_at', 'DESC')->paginate(10);
    }
  }

  public function getAllJobVacancyByParams($params)
  {
    $data = $this->model
      ->with([
        'company', 'category', 'sub_category', 'company.province', 'company.city', 'company.district',
        'company.sub_district'
      ])
      ->where('isActive', 1)
      ->where('event_id', null)
      ->where('open_until', '>=', date('Y-m-d'));

    $id = $params['id'] ?? '';
    if ($id != '') $data = $data->where('id',  $id);

    $description = $params['description'] ?? '';
    if ($description != '') $data = $data->where('description', 'like', "%$description%");

    $salary_from = $params['salary_from'] ?? '';
    if ($salary_from != '') $data = $data->where(function ($q) use ($salary_from) {
      $q->where('salary_from', '>=', $salary_from)->orWhere('salary_to', '>=', $salary_from);
    });

    $salary_to = $params['salary_to'] ?? '';
    if ($salary_to != '') $data = $data->where(function ($q) use ($salary_to) {
      $q->where('salary_from', '<=', $salary_to)->orWhere('salary_to', '<=', $salary_to);
    });

    $paginate = $params['paginate'] ?? '';
    if ($paginate !== '') return $data->paginate($paginate);
    return $data->get();
  }

  public function getAllJobVacanciesAdmin()
  {
    return $this->model->with(['company' => function($q){
      $q->with('user:id,email')->select('companies.id', 'user_id','company_name', 'nama_HRD', 'phone_number');
    }])->where('event_id', null)->orderby('updated_at', 'DESC')->get();
  }
  public function getAllEventJobVacanciesAdmin()
  {
    return $this->model->with(['company' => function($q){
      $q->with('user:id,email')->select('companies.id', 'user_id','company_name', 'nama_HRD', 'phone_number');
    }, 'event:id,event_name'])->whereHas('event', function ($q) {
      $q->where('institution_id', null);
    })->where('event_id', '!=', null)->orderby('updated_at', 'DESC')->get();
  }
  public function getAllSpecialEventJobVacanciesAdmin()
  {
    return $this->model->with(['company' => function($q){
      $q->with('user:id,email')->select('companies.id', 'user_id','company_name', 'nama_HRD', 'phone_number');
    }, 'event:id,event_name'])->whereHas('event', function ($q) {
      $q->where('institution_id', '!=', null);
    })->where('event_id', '!=', null)->orderby('updated_at', 'DESC')->get();
  }
  public function getAllSpecialEventJobVacanciesOperator($institution_id)
  {
    return $this->model->with(['company' => function($q){
      $q->with('user:id,email')->select('companies.id', 'company_name', 'nama_HRD', 'phone_number');
    }, 'event:id,event_name'])->whereHas('event', function ($q) use ($institution_id) {
      $q->where('institution_id', $institution_id);
    })->where('event_id', '!=', null)->orderby('updated_at', 'DESC')->get();
  }

  public function getApplicantsByCompanyId($id)
  {
    return $this->model->select(
      'job_vacancies.id',
      'job_vacancies.position_name',
      'applicants.job_vacancy_id',
      'applicants.job_seeker_id',
      'applicants.updated_at as pivot_updated_at',
      'job_seekers.id as seeker_id',
      'job_seekers.slug',
      'job_seekers.slug'
    )
      ->join('applicants', 'applicants.job_vacancy_id', '=', 'job_vacancies.id')
      ->join('job_seekers', 'job_seekers.id', '=', 'applicants.job_seeker_id')
      ->where('company_id', $id)->where('open_until', '>=', date('Y-m-d'))
      ->orderby('pivot_updated_at', 'DESC')->limit(10)->get();
  }

  public function countJobVacancies(){
      return $this->model->count();
  }
}
