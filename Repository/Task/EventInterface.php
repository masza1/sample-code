<?php

namespace App\Http\Repository\Task;

interface EventInterface
{
  public function createEvent($validated,$ids);
  public function getAllEvents();
  public function getAllSpecialEvents();
  public function getAllSpecialEventsOperator($institution_id);
  public function getAllEventByCompanyId($id, $query, $isOpen);
  public function getEventByCode($id);
  public function getDetailEvent($event_code);
  public function updateEventById($validated, $ids,$id);
  public function updateRegisteredEvent($idsSeeker, $statusSeeker, $id);
  public function deleteEventById($id);
  public function countEvents();
}
