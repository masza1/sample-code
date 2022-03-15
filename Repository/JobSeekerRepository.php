<?php

namespace App\Http\Repository;

use App\Http\Repository\Task\JobSeekerInterface;
use App\Models\JobSeeker;
use App\Models\User;

class JobSeekerRepository implements JobSeekerInterface
{
  protected $model;

  public function __construct(JobSeeker $jobSeeker)
  {
    $this->model = $jobSeeker;
  }

  public function createJobSeeker($validated)
  {
  }

  public function getJobSeekerById($id)
  {
    return $this->model->find($id);
  }

  public function getJobSeekerWithEducations($id)
  {
    return $this->model->with('educationals', 'experiences', 'requirements')->where('id', $id)->first();
  }

  public function getJobSeekerBySlug($slug)
  {
    return $this->model->where('slug', $slug)->first();
  }

  public function getJobSeekerByNIKs($NIKs)
  {
    $updates = $this->model->without(['province', 'city', 'district', 'sub_district'])->whereIn('NIK', $NIKs)->select('id','NIK', 'fullname', 'phone_number', 'job_status');
    $job_seekers = $updates;
    $updates->update(['job_status' => 'Bekerja (Bekerja) angkatan kerja']);
    return $job_seekers->get();
  }
  
  public function getJobSeekerByNotNIKs($NIKs)
  {
    return $this->model->without(['province', 'city', 'district', 'sub_district'])->whereNotIn('NIK', $NIKs)->select('id','NIK', 'fullname', 'phone_number', 'job_status')->get();
  }

  public function updateJobSeekerById($validated, $id)
  {
    $job_seeker = $this->model->find($id);
    if ($validated['email'] != null) {
      $job_seeker->user->update(['email' => $validated['email']]);
    }

    if ($validated['image'] != null) {
      if (file_exists(public_path('storage/' . $job_seeker->image) && $job_seeker->image != null)) {
        unlink(public_path('storage/' . $job_seeker->image));
      }
    } else {
      unset($validated['image']);
    }
    if ($validated['file_cv'] != null) {
      if (file_exists(public_path('storage/' . $job_seeker->file_cv) && $job_seeker->file_cv != null)) {
        unlink(public_path('storage/' . $job_seeker->file_cv));
      }
    } else {
      unset($validated['file_cv']);
    }
    unset($validated['email']);
    $job_seeker->update($validated);
    return $job_seeker;
  }

  public function deleteJobSeekerById($id)
  {
    $job_seeker =  $this->model->find($id);
    if (file_exists(public_path('storage/' . $job_seeker->file_cv)) && $job_seeker->file_cv != null) {
      unlink(public_path('storage/' . $job_seeker->file_cv));
    }
    if (file_exists(public_path('storage/' . $job_seeker->image)) && $job_seeker->image != null) {
      unlink(public_path('storage/' . $job_seeker->image));
    }
    return $job_seeker->delete();
  }

  public function getJobSeekerWithAllRelation($id, $part = null)
  {
    return $this->model->when($part != null, function ($query) {
      $query->with(['experiences', 'portofolios', 'skills', 'educationals', 'seeker_files:id,job_seeker_id,requirement_id']);
    })->withCount(['applicants as applied' => function ($q) {
      $q->where('event_id', null);
    }, 'applicants as followed_event' => function ($q) {
      $q->where('event_id', '!=', null)->whereHas('event', function ($qr) {
        $qr->where('institution_id', null);
      });
    }, 'applicants as followed_special_event' => function ($q) {
      $q->where('event_id', '!=', null)->whereHas('event', function ($qr) {
        $qr->where('institution_id', '!=', null);
      });
    }])->where('id', $id)->first();
  }

  public function getJobSeekerRecomendations($position_name, $category_id, $institution_id = null)
  {
    return $this->model->withOnly('recomendations:id')->where(function ($q) use ($position_name) {
      $q->where('head_professional', 'LIKE', '%' . $position_name . '%');
      $words = explode(' ', $position_name);
      foreach ($words as $word) {
        $q->orwhere('head_professional', 'LIKE', '%' . $word . '%');
      }
    })->orWhereHas('experiences', function ($q) use ($category_id) {
      $q->where('category_id', $category_id);
    })->where('job_status', '!=', 'Bekerja (Bekerja) angkatan kerja')
      ->where('job_status', '!=', 'Berwirausaha (bekerja) angkatan kerja')
      ->when($institution_id != null, function ($q) use ($institution_id) {
        $q->where('institution_id', $institution_id);
      })/* ->orderBy('fullname', 'ASC') */->get();
  }

  public function getJobSeekerWithAllRelationBySlug($slug)
  {
    return $this->model->with(['experiences', 'portofolios', 'skills', 'educationals'/* , 'seeker_files', */])
      ->where('slug', $slug)->where('isCompleted', 1)->first();
  }

  public function getAllJobSeekerByQuery($query, $province_id, $city_id, $isIndex)
  {
    $job_seekers =  $this->model->with(['skills' => function ($q) use ($query) {
      $q->when($query != null, function ($qr) use ($query) {
        $qr->where('name', 'LIKE', '%' . $query . '%');
      });
    }])->when($province_id != null, function ($q) use ($province_id) {
      $q->where('province_id', $province_id);
    })->when($city_id != null, function ($q) use ($city_id) {
      $q->where('city_id', $city_id);
    })
      ->where('isCompleted', 1);

    if ($isIndex) {
      return $job_seekers->inRandomOrder()->limit(10)->get();
    } else {
      return $job_seekers->inRandomOrder()->paginate(10);
    }
  }

  public function getAllJobSeekerWithUser($province_id = null, $city_id = null, $district_id = null, $sub_district_id = null, $download = false, $isAdmin = true)
  {
    return $this->model->when($download, function ($q) {
      $q->with(['user', 'educationals' => function ($qr) {
        $qr->max('educational_type_id');
      }]);
    }, function ($q) {
      $q->with('user');
    })->when($province_id != null, function ($q) use ($province_id) {
      $q->where('province_id', $province_id);
    })->when($city_id != null, function ($q) use ($city_id) {
      $q->where('city_id', $city_id);
    })->when($district_id != null, function ($q) use ($district_id) {
      $q->where('district_id', $district_id);
    })->when($sub_district_id != null, function ($q) use ($sub_district_id) {
      $q->where('sub_district_id', $sub_district_id);
    })->when(!$isAdmin, function ($q) {
      $q->where('job_status', '!=', 'Bekerja (Bekerja) angkatan kerja')->where('job_status', '!=', 'Berwirausaha (bekerja) angkatan kerja');
    })->orderBy('fullname', 'ASC')->get();
  }

  public function getSeekersByJobId($id, $query, $status)
  {
    return $this->model->with(['applicants' => function ($q) use ($id) {
      $q->where('job_vacancy_id', $id);
    }])->whereHas('applicants', function ($q) use ($id, $status) {
      $q->where('job_vacancy_id', $id)->when($status != null, function ($qr) use ($status) {
        $qr->where('status', $status);
      });
    })->when($query != null, function ($q) use ($query) {
      $q->where('fullname', 'LIKE', '%' . $query . '%');
    })->paginate(10);
  }

  public function getSeekerByJobIdAdmin($job_vacancy_id)
  {
    return $this->model->with(['applicants' => function ($q) use ($job_vacancy_id) {
      $q->where('job_vacancy_id', $job_vacancy_id);
    }, 'user'])->whereHas('applicants', function ($q) use ($job_vacancy_id) {
      $q->where('job_vacancy_id', $job_vacancy_id);
    })->get();
  }

  public function isCompleted($job_seeker_id)
  {
    $completed = 0;
    $job_seeker = $this->model->with(['experiences', 'portofolios', 'skills', 'educationals', 'seeker_files'])->where('id', $job_seeker_id)
      ->where('NIK', '<>', '')->where('no_KK', '<>', '')->where('fullname', '<>', '')->where('head_professional', '<>', '')->where('description', '<>', '')
      ->where('gender', '<>', '')->where('religi', '<>', '')->where('marital_status', '<>', '')->where('job_status', '<>', '')->where('place_of_birth', '<>', '')
      ->where('birthdate', '<>', '')->where('province_id', '<>', '')->where('city_id', '<>', '')->where('district_id', '<>', '')
      ->where('sub_district_id', '<>', '')->where('address', '<>', '')->where('RT', '<>', '')->where('RW', '<>', '')
      ->where('poscode', '<>', '')->where('phone_number', '<>', '')->where('image', '<>', '')->first();
    if ($job_seeker != null) {
      if (!$job_seeker->educationals->isEmpty()) {
        $completed += 1;
      } else {
        $completed -= 1;
      }
      // if (!$job_seeker->experiences->isEmpty()) {
      //   $completed += 1;
      // } else {
      //   $completed -= 1;
      // }
      // if (!$job_seeker->portofolios->isEmpty()) {
      //   $completed += 1;
      // } else {
      //   $completed -= 1;
      // }
      if (!$job_seeker->skills->isEmpty()) {
        $completed += 1;
      } else {
        $completed -= 1;
      }
    }
    $job_seeker = $this->model->where('id', $job_seeker_id)->first();
    $user = User::where('id', $job_seeker->user_id)->first();
    if ($completed == 2) {
      $user->givePermissionTo('apply job');
      $job_seeker->update([
        'isCompleted' => true
      ]);
    } else {
      $user->revokePermissionTo('apply job');
      $job_seeker->update([
        'isCompleted' => false
      ]);
    }
  }

  public function getPartisipantClientByEventCode($event_id, $forExport, $isGroup, $isRegister = false)
  {
    $clients = $this->model->whereHas('events', function ($q) use ($event_id, $isRegister) {
      $q->where('events.id', $event_id)->when($isRegister, function ($q) {
        $q->where('events_job_seekers.registered_status', 'pending');
      });
    })->when($forExport, function ($q) {
      $q->withOnly(['events' => function ($q) {
        $q->select('events.id')->without(['province', 'city', 'institution']);
      }, 'educationals' => function ($qr) {
        $qr->max('educational_type_id');
      }])->whereHas('educationals')
        ->select('job_seekers.id', 'user_id', 'fullname', 'NIK', 'phone_number', 'address', 'place_of_birth', 'birthdate', 'gender', 'head_professional', 'city_id');
    })->when(!$forExport, function ($q) {
      $q->withOnly(['user:id,email', 'events' => function ($q) {
        $q->select('events.id')->without(['province', 'city', 'institution']);
      }])
        ->select('job_seekers.id', 'user_id', 'fullname', 'gender', 'head_professional');
    })->orderBy('fullname', 'ASC');

    if ($isGroup) {
      return $clients->get()->groupBy(function ($q) {
        if ($q->city_id == '3515') {
          return 'SIDOARJO';
        } else {
          return 'LUAR SIDOARJO';
        }
      });
    } else {
      return $clients->get();
    }
  }

  public function countJobSeekers()
  {
    return $this->model->count();
  }

  public function getAllSeekerGroupStatus($year)
  {
    $year = $year == null ? date('Y') : $year;
    return $this->model->without('province', 'city', 'district', 'sub_district')->select('id', 'job_status', 'updated_at')->whereYear('updated_at', $year)->orderBy('updated_at', 'ASC')->get()->groupBy(function ($q) {
      if ($q->job_status == 'Bekerja (Bekerja) angkatan kerja') {
        return 'Bekerja';
      } else if ($q->job_status == 'Pelajar/Mahasiswa (Sekolah) bukan angkatan kerja') {
        return 'Pelajar/Mahasiswa';
      } else if ($q->job_status == 'Berwirausaha (bekerja) angkatan kerja') {
        return 'Wirausaha';
      } else if ($q->job_status == 'Mengurus Rumah Tangga (Mengurus Rumah Tangga) bukan angkatan kerja') {
        return 'IRT';
      } else if ($q->job_status == 'Tidak Bekerja (Penganggur) bukan angkatan kerja') {
        return 'Tidak Bekerja';
      } else if ($q->job_status == 'Lainnya (Lainnya) bukan angkatan kerja') {
        return 'Lainnya';
      } else if ($q->job_status == '' || $q->job_status == null) {
        return 'Belum mengisi';
      }
    });
  }
}
