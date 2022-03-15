<?php

namespace App\Http\Repository;

use App\Http\Repository\Task\EventInterface;
use App\Models\Event;

class EventRepository implements EventInterface
{
  protected $model;

  public function __construct(Event $Event)
  {
    $this->model = $Event;
  }

  public function createEvent($validated, $ids)
  {
    $event = $this->model->create($validated);
    return $event->companies()->sync($ids);
  }

  public function getAllEvents()
  {
    return $this->model->where('isSpecialEvent', 0)->orderby('created_at', 'DESC')->get();
  }

  public function getAllSpecialEvents()
  {
    return $this->model->where('isSpecialEvent', 1)->orderby('created_at', 'DESC')->get();
  }

  public function getAllSpecialEventsOperator($institution_id)
  {
    return $this->model->where('isSpecialEvent', 1)->where('institution_id', $institution_id)->orderby('created_at', 'DESC')->get();
  }

  public function getEventByCode($id)
  {
    return $this->model->where('event_code', $id)->first();
  }

  public function getAllEventByCompanyId($id, $query, $isOpen)
  {
    return $this->model->whereHas('companies', function ($q) use ($id) {
      $q->where('companies.id', $id)->where('status', 'active');
    })->when($query != null, function ($qr) use ($query) {
      $qr->where('event_name', 'LIKE', '%' . $query . '%');
    })->when($isOpen != null, function ($qr) use ($isOpen) {
      if ($isOpen == 'active') {
        $qr->where('date_start', '>=', date('Y-m-d'));
      } else if ($isOpen == 'expired') {
        $qr->where('date_end', '<=', date('Y-m-d'));
      }
    })->orderBy('updated_at', 'DESC')->paginate(10);
  }

  public function updateEventById($validated, $ids, $id)
  {
    $event = $this->model->where('event_code',  $id)->first();
    if ($validated['logo'] != null) {
      if (file_exists(public_path('storage/' . $event->logo)) && $event->logo != null) {
        unlink(public_path('storage/' . $event->logo));
      }
    } else {
      unset($validated['logo']);
    }
    if ($validated['image_banner'] != null) {
      if (file_exists(public_path('storage/' . $event->image_banner)) && $event->image_banner != null) {
        unlink(public_path('storage/' . $event->image_banner));
      }
    } else {
      unset($validated['image_banner']);
    }

    // if ($changeList) {
    $event->companies()->sync($ids);
    // } else {
    // $event->companies()->syncWithoutDetaching($ids);
    // }
    return $event->update($validated);
  }

  public function updateRegisteredEvent($idsSeeker, $statusSeeker, $id)
  {
    $event = $this->model->where('event_code',  $id)->first();
    $idsApproved = [];
    $idsRejected = [];
    foreach ($statusSeeker as $key => $value) {
      if ($value == 'approved') {
        array_push($idsApproved, $idsSeeker[$key]);
      } else {
        array_push($idsRejected, $idsSeeker[$key]);
      }
    }
    $event->job_seekers()->updateExistingPivot($idsApproved, ['registered_status' => 'approved']);
    $event->job_seekers()->updateExistingPivot($idsRejected, ['registered_status' => 'rejected']);
    return true;
  }

  public function deleteEventById($id)
  {
    $event  = $this->model->where('event_code', $id)->first();
    if ($event->job_vacancies != null) {
      return 501;
    }
    $logo = $event->logo;
    $image_banner = $event->image_banner;
    if ($event->delete()) {
      if (file_exists(public_path('storage/' . $logo)) && $logo != null) {
        unlink(public_path('storage/' . $logo));
      }
      if (file_exists(public_path('storage/' . $image_banner)) && $image_banner != null) {
        unlink(public_path('storage/' . $image_banner));
      }
      return 200;
    }
    return 400;
  }

  public function searchEventPublic($query, $province_id, $city_id, $isSpecial, $institution_id)
  {
    return $this->model->with(['companies' => function ($q) {
      $q->where('status', 'active');
    }])->when($query != null, function ($q) use ($query) {
      $q->where('event_name', 'LIKE', '%' . $query . '%');
    })->when($province_id != null, function ($q) use ($province_id) {
      $q->where('province_id', $province_id);
    })->when($city_id != null, function ($q) use ($city_id) {
      $q->where('city_id', $city_id);
    })->when($isSpecial && $institution_id != null, function ($q) use ($institution_id) {
      $q->where('institution_id', $institution_id);
    })->where('isSpecialEvent', $isSpecial)->where(function ($q) {
      // $q->where('date_start', '<=', date('Y-m-d'));
      $q->where('date_end', '>=', date('Y-m-d'));
    })->where('status', 'publish')
      ->orderBy('created_at', 'DESC')->paginate(10);
  }

  public function getDetailEvent($event_code)
  {
    return $this->model->with(['companies' => function ($q) {
      $q->where('status', 'active');
    }])->where('status', 'publish')
      ->where('event_code', $event_code)->first();
  }

  public function countEvents()
  {
    return $this->model->count();
  }
}
