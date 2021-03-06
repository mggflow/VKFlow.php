<?php

namespace MGGFLOW\VKFlow;

use MGGFLOW\Tools\TimeMeter;
use MGGFLOW\VKFlow\Exceptions\ProfileNotFound;
use MGGFLOW\VKFlow\Exceptions\TasksNotFound;
use MGGFLOW\VKFlow\Interfaces\ProfileData;
use MGGFLOW\VKFlow\Interfaces\TaskFabric;
use MGGFLOW\VKFlow\Interfaces\TaskQueue;
use MGGFLOW\VKFlow\Interfaces\TaskResultHandlerFabric;

/**
 * Worker to perform Tasks.
 */
class Worker
{
    protected TaskQueue $taskQueue;
    protected TaskFabric $taskFabric;
    protected ProfileData $profileData;
    protected TaskResultHandlerFabric $taskResultHandlerFabric;
    protected TimeMeter $timeMeter;

    public function __construct(
        ProfileData $profileData,
        TaskQueue $taskQueue, TaskFabric $taskFabric, TaskResultHandlerFabric $taskResultHandlerFabric
    )
    {
        $this->timeMeter = new TimeMeter();
        $this->profileData = $profileData;
        $this->taskQueue = $taskQueue;
        $this->taskFabric = $taskFabric;
        $this->taskResultHandlerFabric = $taskResultHandlerFabric;
    }

    /**
     * Perform one task.
     * @return void
     * @throws ProfileNotFound
     * @throws TasksNotFound
     */
    public function perform()
    {
        $taskNote = $this->taskQueue->shiftTask();
        if (empty($taskNote)) {
            throw new TasksNotFound();
        }

        $profile = $this->profileData->getById($taskNote->profile_id);
        if (empty($profile)) {
            throw new ProfileNotFound();
        }

        $task = $this->taskFabric->make($taskNote, $profile);

        $result = $task->execute();
        $result->elapsedTime = $this->timeMeter->timeBetween();

        $resultHandler = $this->taskResultHandlerFabric->make($taskNote, $profile, $this->taskFabric->getApi(), $result);
        $resultHandler->handle();
    }
}