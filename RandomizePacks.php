<?php

namespace App\Console\Commands;

use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class RandomizePacks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:randomize-pack';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a JSON with randomized pack distribution';



    // 9799 Sparks (200 out of the packs)
    // 22597 Boosters (350 Legendary, 3500 Epic, 18747 Rare)
    // 12798 Components (150 Legendary, 1000 Epic, 3000 Rare)

    protected $packStandardCount = 6800;
    protected $packDeluxeCount = 2999;

    protected $packStandardBoosterRareTotal = 12270;
    protected $packStandardBoosterEpicTotal = 1225;
    protected $packStandardBoosterLegendaryTotal = 105;

    protected $packDeluxeBoosterRareTotal = 6477;
    protected $packDeluxeBoosterEpicTotal = 2275;
    protected $packDeluxeBoosterLegendaryTotal = 245;

    protected $packStandardComponentCommonTotal = 4970;
    protected $packStandardComponentRareTotal = 1500;
    protected $packStandardComponentEpicTotal = 300;
    protected $packStandardComponentLegendaryTotal = 30;

    protected $packDeluxeComponentCommonTotal = 3678;
    protected $packDeluxeComponentRareTotal = 1500;
    protected $packDeluxeComponentEpicTotal = 700;
    protected $packDeluxeComponentLegendaryTotal = 120;

    protected $packStandardBoosterCount = 2;
    protected $packDeluxeBoosterCount = 3;

    protected $packStandardComponentCount = 1;
    protected $packDeluxeComponentCount = 2;


    protected $packBoosterRareTotal;
    protected $packBoosterEpicTotal;
    protected $packBoosterLegendaryTotal;

    protected $packStandardBoosterTotal;
    protected $packDeluxeBoosterTotal;

    protected $packComponentCommonTotal;
    protected $packComponentRareTotal;
    protected $packComponentEpicTotal;
    protected $packComponentLegendaryTotal;

    protected $packStandardComponentTotal;
    protected $packDeluxeComponentTotal;


    protected $packSparkTotal;
    protected $packBoosterTotal;
    protected $packComponentTotal;

    protected $sparks;
    protected $boostersRare;
    protected $boostersEpic;
    protected $boostersLegendary;
    protected $componentsCommon;
    protected $componentsRare;
    protected $componentsEpic;
    protected $componentsLegendary;

    protected $boostersAllCount;
    protected $componentsAllCount;

    protected $randArr = [];

    protected $maxVariance = 10;



    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->packBoosterRareTotal = $this->packStandardBoosterRareTotal + $this->packDeluxeBoosterRareTotal;
        $this->packBoosterEpicTotal = $this->packStandardBoosterEpicTotal + $this->packDeluxeBoosterEpicTotal;
        $this->packBoosterLegendaryTotal = $this->packStandardBoosterLegendaryTotal + $this->packDeluxeBoosterLegendaryTotal;

        $this->packStandardBoosterTotal = $this->packStandardBoosterCount * $this->packStandardCount;
        $this->packDeluxeBoosterTotal = $this->packDeluxeBoosterCount * $this->packDeluxeCount;

        $this->packComponentCommonTotal = $this->packStandardComponentCommonTotal + $this->packDeluxeComponentCommonTotal;
        $this->packComponentRareTotal = $this->packStandardComponentRareTotal + $this->packDeluxeComponentRareTotal;
        $this->packComponentEpicTotal = $this->packStandardComponentEpicTotal + $this->packDeluxeComponentEpicTotal;
        $this->packComponentLegendaryTotal = $this->packStandardComponentLegendaryTotal + $this->packDeluxeComponentLegendaryTotal;

        $this->packStandardComponentTotal = $this->packStandardComponentCount * $this->packStandardCount;
        $this->packDeluxeComponentTotal = $this->packDeluxeComponentCount * $this->packDeluxeCount;

        $this->packSparkTotal = $this->packStandardCount + $this->packDeluxeCount;
        $this->packBoosterTotal = $this->packStandardBoosterTotal + $this->packDeluxeBoosterTotal;
        $this->packComponentTotal = $this->packStandardComponentTotal + $this->packDeluxeComponentTotal;

        $this->reloadFromDb();

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $this->preCheck();


        if (!$this->confirm('Continue with the randomization?', true)) {
            return 0;
        }

        $this->maxVariance = 0; //intval($this->ask('What is the maximum Variance accepted?'));

        $finalCheck = false;


        while(!$finalCheck){
            $this->info('Randomizing array');
            $this->randomizeArray(true);
            $this->info('Intermediate check');
            $finalCheck = $this->finalCheck();
        }


        $fileName = 'app/randomizer/pack_randomized_'.Carbon::now()->format('Y-m-d-H-i-s').'.json';
        Storage::disk('localstorage')->put($fileName, collect($this->randArr)->toJson());
        $this->info('All done! Randomization completed and stored in file ' . $fileName);

        $this->info('Final check for correct distribution');
        $this->finalCheck(true);

        $this->info('Process completed succesfully!');
    }


    protected function reloadFromDb(){
        $this->sparks = FlovatarComponent::readyForPack()->whereCategory('spark')->get();
        $this->boostersRare = FlovatarComponent::readyForPack()->whereBoosterRarity('rare')->inRandomOrder()->get();
        $this->boostersEpic = FlovatarComponent::readyForPack()->whereBoosterRarity('epic')->inRandomOrder()->get();
        $this->boostersLegendary = FlovatarComponent::readyForPack()->whereBoosterRarity('legendary')->inRandomOrder()->get();
        $this->componentsCommon = FlovatarComponent::readyForPack()->whereComponentRarity('common')->inRandomOrder()->get();
        $this->componentsRare = FlovatarComponent::readyForPack()->whereComponentRarity('rare')->inRandomOrder()->get();
        $this->componentsEpic = FlovatarComponent::readyForPack()->whereComponentRarity('epic')->inRandomOrder()->get();
        $this->componentsLegendary = FlovatarComponent::readyForPack()->whereComponentRarity('legendary')->inRandomOrder()->get();

        $this->boostersAllCount = $this->boostersRare->count() + $this->boostersEpic->count() + $this->boostersLegendary->count();
        $this->componentsAllCount = $this->componentsCommon->count() + $this->componentsRare->count() + $this->componentsEpic->count() + $this->componentsLegendary->count();
    }



    protected function preCheck(){
        $this->info('Pack Standard Count: '.$this->packStandardCount);
        $this->info('Pack Deluxe Count: '.$this->packDeluxeCount);
        $this->info('Spark Required: '.$this->packSparkTotal);
        $this->info('Spark Available: '.$this->sparks->count());
        $this->info('Boosters Rare Available: '.$this->boostersRare->count());
        $this->info('Boosters Epic Available: '.$this->boostersEpic->count());
        $this->info('Boosters Legendary Available: '.$this->boostersLegendary->count());
        $this->info('Boosters Required: '.$this->packBoosterTotal);
        $this->info('Boosters Available: '.$this->boostersAllCount);
        $this->info('Components Common Available: '.$this->componentsCommon->count());
        $this->info('Components Rare Available: '.$this->componentsRare->count());
        $this->info('Components Epic Available: '.$this->componentsEpic->count());
        $this->info('Components Legendary Available: '.$this->componentsLegendary->count());
        $this->info('Component Required: '.$this->packComponentTotal);
        $this->info('Components Available: '.$this->componentsAllCount);

        if($this->sparks->count() < $this->packSparkTotal){
            $this->error('Not enough Sparks!');
            return 0;
        }
        if($this->componentsAllCount < $this->packComponentTotal){
            $this->error('Not enough Components available!');
            return 0;
        }
        if($this->componentsCommon->count() < $this->packComponentCommonTotal){
            $this->error('Not enough Common Components available!');
            return 0;
        }
        if($this->componentsRare->count() < $this->packComponentRareTotal){
            $this->error('Not enough Rare Components available!');
            return 0;
        }
        if($this->componentsEpic->count() < $this->packComponentEpicTotal){
            $this->error('Not enough Epic Components available!');
            return 0;
        }
        if($this->componentsLegendary->count() < $this->packComponentLegendaryTotal){
            $this->error('Not enough Legendary Components available!');
            return 0;
        }

        if($this->boostersAllCount < $this->packBoosterTotal){
            $this->error('Not enough Booster available!');
            return 0;
        }
        if($this->boostersRare->count() < $this->packBoosterRareTotal){
            $this->error('Not enough Rare Booster available!');
            return 0;
        }
        if($this->boostersEpic->count() < $this->packBoosterEpicTotal){
            $this->error('Not enough Epic Booster available!');
            return 0;
        }
        if($this->boostersLegendary->count() < $this->packBoosterLegendaryTotal){
            $this->error('Not enough Legendary Booster available!');
            return 0;
        }


    }


    protected function randomizePacks(){
        $deluxePacks = collect($this->randArr['deluxe']);
        $standardPacks = collect($this->randArr['standard']);

        // Using cryptographically secure pseudo-random generator
        // https://www.php.net/manual/en/function.random-int.php
        $shuffleAmount = random_int(100,1000);

        for($i=0; $i < $shuffleAmount; $i++){
            $deluxePacks = $deluxePacks->shuffle();
            $standardPacks = $standardPacks->shuffle();
        }

        $this->randArr['deluxe'] = $deluxePacks->toArray();
        $this->randArr['standard'] = $standardPacks->toArray();
    }



    protected function randomizeArray($showErrors = false){


        $this->reloadFromDb();


        $this->randArr = [
            'deluxe' => [],
            'standard' => []
        ];


        for($i = 0; $i < $this->packSparkTotal; $i++){
            $sparkId = null;

            if($this->sparks->count() > 0){
                $temp = $this->sparks->shift();
                $sparkId = $temp->flow_id;
            }
            $type = ($i < $this->packDeluxeCount) ? 'deluxe' : 'standard';

            $packArr = [
                'type' =>  $type,
                'spark' => $sparkId,
                'boost' => [],
                'components' => []
            ];

            $this->randArr[$type][] = $packArr;
        }



        $this->randomizePacks();
        $deluxeCount = 0;
        $standardCount = 0;
        for($i = 0; $i < $this->packBoosterLegendaryTotal; $i++){
            $temp = $this->boostersLegendary->shift();
            $type = ($i < $this->packDeluxeBoosterLegendaryTotal) ? 'deluxe' : 'standard';
            if($type == 'deluxe'){
                $this->randArr['deluxe'][$deluxeCount]['boost'][$temp->flow_id] = 'legendary';
                $deluxeCount++;
            } else {
                $this->randArr['standard'][$standardCount]['boost'][$temp->flow_id] = 'legendary';
                $standardCount++;
            }
        }

        $this->randomizePacks();
        $deluxeCount = 0;
        $standardCount = 0;
        for($i = 0; $i < $this->packBoosterEpicTotal; $i++){
            $temp = $this->boostersEpic->shift();
            $type = ($i < $this->packDeluxeBoosterEpicTotal) ? 'deluxe' : 'standard';
            if($type == 'deluxe'){
                while(count($this->randArr['deluxe'][$deluxeCount]['boost']) >= $this->packDeluxeBoosterCount && $deluxeCount < count($this->randArr['deluxe'])){
                    $deluxeCount++;
                }
                $this->randArr['deluxe'][$deluxeCount]['boost'][$temp->flow_id] = 'epic';
                $deluxeCount++;
            } else {
                while(count($this->randArr['standard'][$standardCount]['boost']) >= $this->packStandardBoosterCount && $standardCount < count($this->randArr['standard'])){
                    $standardCount++;
                }
                $this->randArr['standard'][$standardCount]['boost'][$temp->flow_id] = 'epic';
                $standardCount++;
            }
        }

        for($i = 0; $i < $this->packDeluxeCount; $i++){
            for ($j = count($this->randArr['deluxe'][$i]['boost']); $j < $this->packDeluxeBoosterCount && $this->boostersRare->count() > 0; $j++) {
                $temp = $this->boostersRare->shift();
                $this->randArr['deluxe'][$i]['boost'][$temp->flow_id] = 'rare';
            }
        }
        for($i = 0; $i < $this->packStandardCount; $i++){
            for ($j = count($this->randArr['standard'][$i]['boost']); $j < $this->packStandardBoosterCount && $this->boostersRare->count() > 0; $j++) {
                $temp = $this->boostersRare->shift();
                $this->randArr['standard'][$i]['boost'][$temp->flow_id] = 'rare';
            }
        }




        $this->randomizePacks();
        $deluxeCount = 0;
        $standardCount = 0;
        for($i = 0; $i < $this->packComponentLegendaryTotal; $i++){
            $temp = $this->componentsLegendary->shift();
            $type = ($i < $this->packDeluxeComponentLegendaryTotal) ? 'deluxe' : 'standard';
            if($type == 'deluxe'){
                $this->randArr['deluxe'][$deluxeCount]['components'][$temp->flow_id] = 'legendary';
                $deluxeCount++;
            } else {
                $this->randArr['standard'][$standardCount]['components'][$temp->flow_id] = 'legendary';
                $standardCount++;
            }
        }

        $this->randomizePacks();
        $deluxeCount = 0;
        $standardCount = 0;
        for($i = 0; $i < $this->packComponentEpicTotal; $i++){
            $temp = $this->componentsEpic->shift();
            $type = ($i < $this->packDeluxeComponentEpicTotal) ? 'deluxe' : 'standard';
            if($type == 'deluxe'){
                while(count($this->randArr['deluxe'][$deluxeCount]['components']) >= $this->packDeluxeComponentCount && $deluxeCount < count($this->randArr['deluxe'])){
                    $deluxeCount++;
                }
                $this->randArr['deluxe'][$deluxeCount]['components'][$temp->flow_id] = 'epic';
                $deluxeCount++;
            } else {
                while(count($this->randArr['standard'][$standardCount]['components']) >= $this->packStandardComponentCount && $standardCount < count($this->randArr['standard'])){
                    $standardCount++;
                }
                $this->randArr['standard'][$standardCount]['components'][$temp->flow_id] = 'epic';
                $standardCount++;
            }
        }

        $this->randomizePacks();
        $deluxeCount = 0;
        $standardCount = 0;
        for($i = 0; $i < $this->packComponentRareTotal; $i++){
            $temp = $this->componentsRare->shift();
            $type = ($i < $this->packDeluxeComponentRareTotal) ? 'deluxe' : 'standard';
            if($type == 'deluxe'){
                while(count($this->randArr['deluxe'][$deluxeCount]['components']) >= $this->packDeluxeComponentCount && $deluxeCount < count($this->randArr['deluxe'])){
                    $deluxeCount++;
                }
                $this->randArr['deluxe'][$deluxeCount]['components'][$temp->flow_id] = 'rare';
                $deluxeCount++;
            } else {
                while(count($this->randArr['standard'][$standardCount]['components']) >= $this->packStandardComponentCount && $standardCount < count($this->randArr['standard'])){
                    $standardCount++;
                }
                $this->randArr['standard'][$standardCount]['components'][$temp->flow_id] = 'rare';
                $standardCount++;
            }
        }


        for($i = 0; $i < $this->packDeluxeCount; $i++){
            for ($j = count($this->randArr['deluxe'][$i]['components']); $j < $this->packDeluxeComponentCount && $this->componentsCommon->count() > 0; $j++) {
                $temp = $this->componentsCommon->shift();
                $this->randArr['deluxe'][$i]['components'][$temp->flow_id] = 'common';
            }
        }
        for($i = 0; $i < $this->packStandardCount; $i++){
            for ($j = count($this->randArr['standard'][$i]['components']); $j < $this->packStandardComponentCount && $this->componentsCommon->count() > 0; $j++) {
                $temp = $this->componentsCommon->shift();
                $this->randArr['standard'][$i]['components'][$temp->flow_id] = 'common';
            }
        }


        if($showErrors) {
            $this->info('Spark left ' . $this->sparks->count());
            $this->info('Booster Rare left ' . $this->boostersRare->count());
            $this->info('Booster Epic left ' . $this->boostersEpic->count());
            $this->info('Booster Legendary left ' . $this->boostersLegendary->count());
            $this->info('Components Common left ' . $this->componentsCommon->count());
            $this->info('Components Rare left ' . $this->componentsRare->count());
            $this->info('Components Epic left ' . $this->componentsEpic->count());
            $this->info('Components Legendary left ' . $this->componentsLegendary->count());
        }

    }





    protected function finalCheck($showErrors = false){

        $check = true;

        $checkPackStandardRareCount = 0;
        $checkPackStandardEpicCount = 0;
        $checkPackStandardLegendaryCount = 0;
        $checkPackDeluxeRareCount = 0;
        $checkPackDeluxeEpicCount = 0;
        $checkPackDeluxeLegendaryCount = 0;
        $checkPackStandardComponentCommonCount = 0;
        $checkPackStandardComponentRareCount = 0;
        $checkPackStandardComponentEpicCount = 0;
        $checkPackStandardComponentLegendaryCount = 0;
        $checkPackDeluxeComponentCommonCount = 0;
        $checkPackDeluxeComponentRareCount = 0;
        $checkPackDeluxeComponentEpicCount = 0;
        $checkPackDeluxeComponentLegendaryCount = 0;

        $checkPackStandardSparkCount = 0;
        $checkPackDeluxeSparkCount = 0;

        foreach($this->randArr['deluxe'] as $key => $pack) {
            if ($pack['spark']) {
                $checkPackDeluxeSparkCount++;
            } else {
                if ($showErrors) {
                    $this->error('Missing Spark on Deluxe Pack ' . $key);
                }
                $check = false;
            }

            foreach ($pack['boost'] as $rarity) {
                if ($rarity == 'rare') {
                    $checkPackDeluxeRareCount++;
                }
                if ($rarity == 'epic') {
                    $checkPackDeluxeEpicCount++;
                }
                if ($rarity == 'legendary') {
                    $checkPackDeluxeLegendaryCount++;
                }
            }

            foreach ($pack['components'] as $rarity) {
                if ($rarity == 'common') {
                    $checkPackDeluxeComponentCommonCount++;
                }
                if ($rarity == 'rare') {
                    $checkPackDeluxeComponentRareCount++;
                }
                if ($rarity == 'epic') {
                    $checkPackDeluxeComponentEpicCount++;
                }
                if ($rarity == 'legendary') {
                    $checkPackDeluxeComponentLegendaryCount++;
                }
            }

            if (count($pack['boost']) != $this->packDeluxeBoosterCount) {
                if ($showErrors) {
                    $this->error('Invalid Booster count on Deluxe Pack ' . $key . ' - Found ' . count($pack['boost']) . ' Boosters');
                }
                $check = false;
            }
            if (count($pack['components']) != $this->packDeluxeComponentCount) {
                if ($showErrors) {
                    $this->error('Invalid Component count on Deluxe Pack ' . $key . ' - Found ' . count($pack['components']) . ' Components');
                }
                $check = false;
            }
        }

        foreach($this->randArr['standard'] as $key => $pack){
            if($pack['spark']){
                $checkPackStandardSparkCount++;
            } else {
                if($showErrors) {
                    $this->error('Missing Spark on Standard Pack '.$key);
                }
                $check = false;
            }

            foreach($pack['boost'] as $rarity){
                if($rarity == 'rare'){
                    $checkPackStandardRareCount++;
                }
                if($rarity == 'epic'){
                    $checkPackStandardEpicCount++;
                }
                if($rarity == 'legendary'){
                    $checkPackStandardLegendaryCount++;
                }
            }

            foreach($pack['components'] as $rarity){
                if($rarity == 'common'){
                    $checkPackStandardComponentCommonCount++;
                }
                if($rarity == 'rare'){
                    $checkPackStandardComponentRareCount++;
                }
                if($rarity == 'epic'){
                    $checkPackStandardComponentEpicCount++;
                }
                if($rarity == 'legendary'){
                    $checkPackStandardComponentLegendaryCount++;
                }
            }

            if(count($pack['boost']) != $this->packStandardBoosterCount){
                if($showErrors) {
                    $this->error('Invalid Booster count on Standard Pack '.$key.' - Found '.count($pack['boost']).' Boosters');
                }
                $check = false;
            }
            if(count($pack['components']) != $this->packStandardComponentCount){
                if($showErrors) {
                    $this->error('Invalid Component count on Standard Pack '.$key.' - Found '.count($pack['components']).' Components');
                }
                $check = false;
            }
        }

        if($showErrors) {

            if ($checkPackStandardRareCount != $this->packStandardBoosterRareTotal) {
                $this->error('Invalid Total Rare Booster for Standard Packs. Found ' . $checkPackStandardRareCount . ' - Expected ' . $this->packStandardBoosterRareTotal . ' = Diff ' . ($checkPackStandardRareCount - $this->packStandardBoosterRareTotal));
            }
            if ($checkPackStandardEpicCount != $this->packStandardBoosterEpicTotal) {
                $this->error('Invalid Total Epic Booster for Standard Packs. Found ' . $checkPackStandardEpicCount . ' - Expected ' . $this->packStandardBoosterEpicTotal . ' = Diff ' . ($checkPackStandardEpicCount - $this->packStandardBoosterEpicTotal));
            }
            if ($checkPackStandardLegendaryCount != $this->packStandardBoosterLegendaryTotal) {
                $this->error('Invalid Total Legendary Booster for Standard Packs. Found ' . $checkPackStandardLegendaryCount . ' - Expected ' . $this->packStandardBoosterLegendaryTotal . ' = Diff ' . ($checkPackStandardLegendaryCount - $this->packStandardBoosterLegendaryTotal));
            }

            if ($checkPackDeluxeRareCount != $this->packDeluxeBoosterRareTotal) {
                $this->error('Invalid Total Rare Booster for Deluxe Packs. Found ' . $checkPackDeluxeRareCount . ' - Expected ' . $this->packDeluxeBoosterRareTotal . ' = Diff ' . ($checkPackDeluxeRareCount - $this->packDeluxeBoosterRareTotal));
            }
            if ($checkPackDeluxeEpicCount != $this->packDeluxeBoosterEpicTotal) {
                $this->error('Invalid Total Epic Booster for Deluxe Packs. Found ' . $checkPackDeluxeEpicCount . ' - Expected ' . $this->packDeluxeBoosterEpicTotal . ' = Diff ' . ($checkPackDeluxeEpicCount - $this->packDeluxeBoosterEpicTotal));
            }
            if ($checkPackDeluxeLegendaryCount != $this->packDeluxeBoosterLegendaryTotal) {
                $this->error('Invalid Total Legendary Booster for Deluxe Packs. Found ' . $checkPackDeluxeLegendaryCount . ' - Expected ' . $this->packDeluxeBoosterLegendaryTotal . ' = Diff ' . ($checkPackDeluxeLegendaryCount - $this->packDeluxeBoosterLegendaryTotal));
            }



            if ($checkPackStandardComponentCommonCount != $this->packStandardComponentCommonTotal) {
                $this->error('Invalid Total Common Components for Standard Packs. Found ' . $checkPackStandardComponentCommonCount . ' - Expected ' . $this->packStandardComponentCommonTotal . ' = Diff ' . ($checkPackStandardComponentCommonCount - $this->packStandardComponentCommonTotal));
            }
            if ($checkPackStandardComponentRareCount != $this->packStandardComponentRareTotal) {
                $this->error('Invalid Total Rare Booster for Standard Packs. Found ' . $checkPackStandardComponentRareCount . ' - Expected ' . $this->packStandardComponentRareTotal . ' = Diff ' . ($checkPackStandardComponentRareCount - $this->packStandardComponentRareTotal));
            }
            if ($checkPackStandardComponentEpicCount != $this->packStandardComponentEpicTotal) {
                $this->error('Invalid Total Epic Booster for Standard Packs. Found ' . $checkPackStandardComponentEpicCount . ' - Expected ' . $this->packStandardComponentEpicTotal . ' = Diff ' . ($checkPackStandardComponentEpicCount - $this->packStandardComponentEpicTotal));
            }
            if ($checkPackStandardComponentLegendaryCount != $this->packStandardComponentLegendaryTotal) {
                $this->error('Invalid Total Legendary Booster for Standard Packs. Found ' . $checkPackStandardComponentLegendaryCount . ' - Expected ' . $this->packStandardComponentLegendaryTotal . ' = Diff ' . ($checkPackStandardComponentLegendaryCount - $this->packStandardComponentLegendaryTotal));
            }

            if ($checkPackDeluxeComponentCommonCount != $this->packDeluxeComponentCommonTotal) {
                $this->error('Invalid Total Common Components for Deluxe Packs. Found ' . $checkPackDeluxeComponentCommonCount . ' - Expected ' . $this->packDeluxeComponentCommonTotal . ' = Diff ' . ($checkPackDeluxeComponentCommonCount - $this->packDeluxeComponentCommonTotal));
            }
            if ($checkPackDeluxeComponentRareCount != $this->packDeluxeComponentRareTotal) {
                $this->error('Invalid Total Rare Booster for Deluxe Packs. Found ' . $checkPackDeluxeComponentRareCount . ' - Expected ' . $this->packDeluxeComponentRareTotal . ' = Diff ' . ($checkPackDeluxeComponentRareCount - $this->packDeluxeComponentRareTotal));
            }
            if ($checkPackDeluxeComponentEpicCount != $this->packDeluxeComponentEpicTotal) {
                $this->error('Invalid Total Epic Booster for Deluxe Packs. Found ' . $checkPackDeluxeComponentEpicCount . ' - Expected ' . $this->packDeluxeComponentEpicTotal . ' = Diff ' . ($checkPackDeluxeComponentEpicCount - $this->packDeluxeComponentEpicTotal));
            }
            if ($checkPackDeluxeComponentLegendaryCount != $this->packDeluxeComponentLegendaryTotal) {
                $this->error('Invalid Total Legendary Booster for Deluxe Packs. Found ' . $checkPackDeluxeComponentLegendaryCount . ' - Expected ' . $this->packDeluxeComponentLegendaryTotal . ' = Diff ' . ($checkPackDeluxeComponentLegendaryCount - $this->packDeluxeComponentLegendaryTotal));
            }


            if ($checkPackStandardSparkCount != $this->packStandardCount) {
                $this->error('Invalid Total Sparks for Standard Packs. Found ' . $checkPackStandardSparkCount . ' - Expected ' . $this->packStandardCount . ' = Diff ' . ($checkPackStandardSparkCount - $this->packStandardCount));
            }
            if ($checkPackDeluxeSparkCount != $this->packDeluxeCount) {
                $this->error('Invalid Total Sparks for Deluxe Packs. Found ' . $checkPackDeluxeSparkCount . ' - Expected ' . $this->packDeluxeCount . ' = Diff ' . ($checkPackDeluxeSparkCount - $this->packDeluxeCount));
            }


            $this->info('All checks completed!');
        }



        if(abs($checkPackStandardRareCount - $this->packStandardBoosterRareTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackStandardEpicCount - $this->packStandardBoosterEpicTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackStandardLegendaryCount - $this->packStandardBoosterLegendaryTotal) > $this->maxVariance){
            $check = false;
        }

        if(abs($checkPackDeluxeRareCount - $this->packDeluxeBoosterRareTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackDeluxeEpicCount - $this->packDeluxeBoosterEpicTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackDeluxeLegendaryCount - $this->packDeluxeBoosterLegendaryTotal) > $this->maxVariance){
            $check = false;
        }



        if(abs($checkPackStandardComponentCommonCount - $this->packStandardComponentCommonTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackStandardComponentRareCount - $this->packStandardComponentRareTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackStandardComponentEpicCount - $this->packStandardComponentEpicTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackStandardComponentLegendaryCount - $this->packStandardComponentLegendaryTotal) > $this->maxVariance){
            $check = false;
        }

        if(abs($checkPackDeluxeComponentCommonCount - $this->packDeluxeComponentCommonTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackDeluxeComponentRareCount - $this->packDeluxeComponentRareTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackDeluxeComponentEpicCount - $this->packDeluxeComponentEpicTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackDeluxeComponentLegendaryCount - $this->packDeluxeComponentLegendaryTotal) > $this->maxVariance){
            $check = false;
        }

        if(abs($checkPackStandardSparkCount - $this->packStandardCount) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackDeluxeSparkCount - $this->packDeluxeCount) > $this->maxVariance){
            $check = false;
        }


        return $check;
    }


}
