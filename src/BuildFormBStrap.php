<?php

declare(strict_types=1);

namespace Src;

use InvalidArgumentException;

class BuildFormBStrap
{
    /**
     * This function is used to build a form
     * it takes an array which denotes the type of question
     * When there is a need for new entries, use the newEnt array.
     */
    private array $entKey;
    private string $dToken;

    private string $token;

    private array $entValue;

    private int $entCount;

    public ?string $ref = null;

    public ?string $year = null;

    public ?string $month = null;


    /**
     * enter the array to create the form 'name'=> 's' s denotes string, 1 integer, date for date, textera for textera and select is an array ['select' followed by the options]
     * mixed - you can use to generate text, number, select, inputButton
     * textera
     * it also autogenerate the token
     * title of section ( work_information => title).
     */
    public function __construct(public array $question)
    {
        $this->token = urlencode(base64_encode((random_bytes(32))));
        setcookie('XSRF-TOKEN', $this->token, [
            'expires' => time() + 3600,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
            'httponly' => false,
        ]);

        $this->entKey = array_keys($this->question);
        $this->entValue = array_values($this->question);
        $this->entCount = count($this->entValue);
        $_SESSION['token'] = $this->token;

        $this->dToken = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
        $_SEESION['deviceHash'] = $this->dToken;

    }



    /**
     * Generates an array of days.
     *
     * @return array ['days' => array, 'selected' => int|null]
     * @throws InvalidArgumentException If day range is invalid
     */
    private function createDay(): array
    {
        return range(1, 31);
    }

    /**
     * Generates an array of months.
     *
     * @return array ['months' => array, 'selected' => string|null]
     */
    private function createMonth(): array
    {
        return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    }

    /**
     * Generates an array of years.
     *
     * @param int $startYear Starting year (default: 1930)
     * @param int|null $endYear Ending year (default: current year)
     * @return array ['years' => array, 'selected' => int|null]
     * @throws InvalidArgumentException If year range is invalid
     */

    private function createYear(int $startYear = 1930, ?int $endYear = null): array
    {
        $endYear = $endYear ?? (int) date('Y');
        if ($startYear > $endYear) {
            throw new InvalidArgumentException('Start year must be less than or equal to end year');
        }
        return range($startYear, $endYear);
    }


    /**
     * important ones are mixed, select-many, setError
     * example - mixed 'spouse' => ['mixed','label' => ["Spouse's name", "Spouse's mobile", "Spouse's Email"],'attribute' => ['spouseName', 'spouseMobile', 'spouseEmail'],'placeholder' => ['Toyin', '23480364168089', "toyin@gmail.com"], 'inputType' => ['text', 'text', 'email'],'icon' => ['<i class="fas fa-user"></i>','<i class="fas fa-user"></i>','<i class="fas fa-envelope-square"></i>']],.
     *
     * example select-many  'married_gender' => ['select-many','label' => ['Marital status', 'gender']'attribute' => ['maritalStatus', 'gender'],'options' => [['select', 'Yes', 'No'],['select', 'Male', 'Female']],'icon' => ['<i class="far fa-kiss-wink-heart"></i>','<i class="fas fa-user-friends"></i>',]],
     *
     * radio
     *
     *
     * example showError  nameKey => showError - the namekey should be the id of the div or form that will release the error. See Login or Register.js for a clear example
     *
     *'jobSuitability'=> [ 'radio', 'Do you have any health conditions that would prevent you from meeting these intrinsic requirements for which the company might need to make reasonable adjustments? (If yes, please be aware that we may need to discuss these with you at your interview)  ', 'Yes', 'No' ],textarea 'rightToWorkMoreInfo'=> ['textarea', 'if you answered yes, what document will you provide to prove this?'],
     *create a noDisplay class which hide the div or element
     */
    public function genForm(): void
    {


        for ($i = 0; $i < $this->entCount; ++$i) {
            $value = isset($_POST['button']) ? $_POST[$this->entKey[$i]] : '';

            $var = preg_replace('/[^0-9A-Za-z@.]/', ' ', $this->entKey[$i]);
            $nameKey = $this->entKey[$i];

            if ($this->entValue[$i] === 'text') {
                echo <<<HTML
                            <div class='mb-3 $nameKey ' id='{$nameKey}_div'>
                                <label for='{$nameKey}' class='form-label'><b>$var</b></label>
                                <input type='text' class='form-control $nameKey' autocomplete='new-$nameKey' placeholder='Please enter your $var' data-original="$value"  name='$nameKey' id='{$nameKey}' value='$value' required>
                                <small id='{$nameKey}_help' class='form-text text-muted'></small>
                                <small id='{$nameKey}_error' class='form-text text-danger'></small>
                            </div>

                    HTML;
            } elseif ($this->entValue[$i][0] === 'text-icon') {
                $fontAwesome = $this->entValue[$i][1];
                echo <<<HTML
                                    <div class="form-group">
                        <label for="{$nameKey}" class="form-label"><b>$var</b></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    $fontAwesome
                                </span>
                            </div>
                            <input type="text" class="form-control" autocomplete="new-$nameKey" placeholder="$var" required name="$nameKey" id="{$nameKey}" data-original="$value" value="$value">
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="fas fa-check fa-xs"></i>
                                </span>
                            </div>
                        </div>
                        <small id="{$nameKey}_help" class="form-text text-muted"></small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>


                    HTML;
            } elseif ($this->entValue[$i] === 'date') {
                echo <<<HTML
                        <div class="mb-3 $nameKey" id="{$nameKey}_div">
                             <label for="{$nameKey}" class="form-label">$var</label>
                        <input type="date" class="form-control $nameKey" autocomplete="username" placeholder="email" name="$nameKey" data-original="$value" id="{$nameKey}" value="$value">

                            <small id="{$nameKey}_help" class="form-text text-muted"></small>
                            <small id="{$nameKey}_error" class="form-text text-danger"></small>
                        </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'select') {
                $options = $this->entValue[$i];
                echo <<<HTML
                    <div class="form-group">
                        <label for="{$nameKey}" class="form-label"><b>$var</b></label>
                        <select class="form-select form-select-lg mb-3" name="$nameKey" data-original="$value" value="$value" id="{$nameKey}">
                            <option value="" disabled selected>Select an option</option>
                    HTML;
                foreach ($options as $option) {
                    echo <<<HTML
                        <option value="$option">$option</option>
                        HTML;
                }
                echo <<<HTML
                        </select>
                        <small id="{$nameKey}_help" class="form-text text-muted"></small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i][0] === 'textarea') {
                echo <<<HTML
                        <div class="mb-3" id="{$nameKey}_div">
                            <label for="{$nameKey}" class="form-label">
                                <b>{$this->entValue[$i][1]}</b></label>
                            <textarea class="form-control" autocomplete="new-$nameKey" data-original="$value" name="{$nameKey}" id="{$nameKey}">$value</textarea>
                            <small id="{$nameKey}_help" class="form-text text-muted"></small>
                            <small id="{$nameKey}_error" class="form-text text-danger"></small>
                        </div>

                    HTML;
            } elseif ($this->entValue[$i] === 'email') {
                echo <<<HTML

                         <div class="mb-3" id='{$nameKey}_div'>
                            <label for="{$nameKey}" class="form-label">Email address</label>
                            <input type="email" class="form-control $nameKey" data-original="$value" autocomplete="username" placeholder="" name="$nameKey" id="{$nameKey}" value="$value">
                             <small id="{$nameKey}_help" class="form-text text-muted"></small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                        </div>

                    HTML;
            } elseif ($this->entValue[$i] === 'password') {
                echo <<<HTML

                    <div class="mb-3" id='{$nameKey}_div'>
                        <label for="{$nameKey}" class="form-label">Password</label>
                        <input type="password" class="form-control $nameKey" data-original="$value" id="{$nameKey}" name="$nameKey" placeholder="Enter your password" autocomplete="new-password" value="$value">
                        <small id="{$nameKey}_help" class="form-text text-muted">Please enter a strong password.</small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>

                    HTML;
            } elseif ($this->entKey[$i] === 'checkbox') {
                echo <<<HTML


                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="" id="$nameKey" checked>
                        <label class="form-check-label" for="$nameKey">
                            {$this->entValue[$i]}
                        </label>
                         <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i] === 'button') {
                echo <<<HTML
                    <div class="mb-3">
                        <button name="button" id="button" type="button" class="btn btn-primary">
                            {$nameKey}
                        </button>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'submit') {
                echo <<<HTML
                        <div class="mb-3">
                            <button name="submit" id="submit" type="submit" class="btn btn-primary submit">
                                Submit
                            </button>
                        </div>  

                    HTML;
            } elseif ($this->entValue[$i] === 'token') {
                echo <<<HTML
                      
                                 <input type="hidden" class="input" id="token" name="token" value="{$this->token}">
                         

                    HTML;
            } elseif ($this->entValue[$i] === 'birthday') {
                $divID = $this->entValue[$i];
                echo <<<HTML
                    <div class="form-group" id="$divID">
                        <label for="$nameKey" class="form-label"><b>$var</b></label>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <select class="form-control form-control-lg" data-original="$value" name="day" id="day">
                                        <option selected value="select">Day</option>

                    HTML;
                foreach ($this->createDay() as $day) {
                    echo "<option value=\"$day\">$day</option>";
                }
                echo <<<HTML
                                        </select>
                                    </div>
                                </div>
                                <p class="help error" id="day_error"></p>
                                  <p class="help" id="day_help"></p>
                            </div>
                            <div class="form-group">
                                <div class="form-group">
                                    <label for="month" class="form-label">Month</label>
                                    <select class="form-control form-control-lg" name="month" id="month">
                                        <option selected value="select">Month</option>
                    HTML;
                foreach ($this->createMonth() as $month) {
                    echo "<option value=\"$month\">$month</option>";
                }
                echo <<<HTML
                            </select>
                            <small id="month_error" class="form-text text-danger"></small>
                            <small id="month_help" class="form-text text-muted"></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-group">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-control form-control-lg" data-original="$value" name="year" id="year">
                                <option selected value="select">Year</option>

                    HTML;
                foreach ($this->createYear() as $year) {
                    echo "<option value=\"$year\">$year</option>";
                }
                echo <<<HTML
                                        </select>
                                    </div>
                                </div>
                                <p class="help" id="year_error"></p>
                                <p class="help" id="year_help"></p>
                            </div>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'slider') {
                $fAwesome = $this->entValue[$i][1];
                $sliderId = $this->entValue[$i][2];
                $inputId = $this->entValue[$i][3];
                echo <<<HTML
                    <div class="form-group">
                        <label for="$nameKey" class="form-label"><b>$var</b></label>
                        <div class="row">
                            <div class="col">
                                <div id="$sliderId"></div>
                            </div>
                            <div class="col">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">
                                            $fAwesome
                                        </span>
                                    </div>
                                    <input type="number" class="form-control is-success" name="$inputId" id="$inputId" value="$value" readonly>
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    </div>
                                </div>
                                <small id="{$inputId}_help" class="form-text text-muted"></small>
                                <small id="{$inputId}_error" class="form-text text-danger"></small>
                            </div>
                        </div>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i][0] === 'mixed') {
                $divID = $this->entKey[$i];
                echo <<<HTML
                              
                                      <div class="row gx-4 " id="$divID">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $label = $this->entValue[$i]['label'][$y];
                    $name = $this->entValue[$i]['attribute'][$y];
                    $value = $this->entValue[$i]['value'][$y] ?? '';
                    $placeholder = $this->entValue[$i]['placeholder'][$y] ?? null;
                    $id = $name;
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $labelType = $this->entValue[$i]['inputType'][$y] ? $this->entValue[$i]['inputType'][$y] : '';
                    $icon = $this->entValue[$i]['icon'][$y] ?? '';
                    $hasIconLeft = (isset($this->entValue[$i]['icon'][$y]) ? 'has-icon-left' : '');
                    $hasImg = ($this->entValue[$i]['img'][$y] ?? '');
                    $multiple = ''; // multiple for file input

                    if ($labelType === 'select') {
                        echo <<<HTML
                                <div class="form-group $name" id="{$name}_div">
                                    <label for="$id" class="form-label"><b>$cleanLabel</b></label>
                                    <div class="input-group mb-3">
                                        
                                        <select class="form-control form-control-lg" id="$id" name="$name">                                                           
                            HTML;

                        if ($this->entValue[$i]['options'][$y]) {
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value) {
                                echo "<option value='$value'> $value </option>";
                            }
                        }
                        // for ($yii = 0; $yii < count($this->entValue[$i]['options'][$yii]); $yii++) {
                        //     echo "<option>" . $this->entValue[$i]['options'][$yii] . "</option>";
                        // }
                        echo <<<HTML
                                        </select>
                                      
                                        <!-- <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="fas fa-angle-down fasCol"></i>
                                            </span>
                                        </div> -->
                                        </div>
                                        <small id="$help" class="form-text text-muted"></small>
                                        <small id="$error" class="form-text text-danger"></small>
                                        </div>
                                        </div>

                            HTML;
                    } elseif ($labelType === 'inputButton') {
                        echo <<<HTML
                            
                                <div class="form-group $name" id="{$name}_div">
                                <div class="input-group mb-3">
                                    
                                    <input type="text" class="form-control is-medium" id="{$name}" name="$name" placeholder="$cleanLabel">
                                </div>
                                <small id="{$name}_help" class="form-text text-muted"></small>
                                <button class="btn btn-success btn-lg btn-block" id="{$name}_button">Search</button>
                                <small id="{$name}_help" class="form-text text-muted"></small>
                                <small id="{$name}_error" class="form-text text-danger"></small>
                            </div>

                            HTML;
                    } elseif ($labelType === 'cardSelect') {
                        echo <<<HTML
                                                <div class="$name col m-1" id="{$name}_div">
                                                    <div class="card h-100 hidden">
                                                <img src="$hasImg" class="card-img-top" alt="...">
                                               
                                                    <div class="card-body">

                                                        <h5 class="card-title">$cleanLabel</h5>

                                                    
                                        
                                                    
                            HTML;
                        if ($this->entValue[$i]['options'][$y]) {
                            echo <<<HTML
                                        <select class="form-select form-select-lg mb-3" arial-label='Default' id="$id" name="$name">
                                            <option value='$value'> 
                                                <span class="option_text">Choose </span>
                                                </option>
                                HTML;
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value => $option) {
                                echo "<option value='$value'>
                                <span class='option_text'> $option 
                                </span> </option>";
                            }
                            echo <<<HTML
                                    </select>
                                HTML;
                        } else {
                            echo <<<HTML
                                    <input type="text" class="form-control" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                                HTML;
                        }
                        echo <<<HTML
                                                   
                                                 
                                               
                                               <small id="$help" class="form-text text-muted"></small>
                                                <small id="$error" class="form-text text-danger"></small> 
                                                </div>
                                            </div>
                                            </div>
                            HTML;
                    } elseif ($labelType === 'file') {

                        if (strpos($name, '[]') !== false) {
                            $name = str_replace(['[', ']'], '', $name);
                            $multiple = "multiple";
                        }




                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label for="{$name}" class="label is-medium"><b>$cleanLabel</b></label>
                                <div class="control is-expanded $hasIconLeft">
                                    <input class="input $name input is-medium" type="$labelType" value="$value" maxlength="30" data-original="$value" value="$value" minlength="1" name="$name" id="{$name}"
                                    placeholder="$placeholder" autocomplete="$name" $multiple>
                                    <span class="icon is-small is-left">$icon</span>
                                    <p class="help" id="{$name}_help"></p>
                                    <p class="help error" id="{$name}_error"></p>
                                </div>
                            </div>
                            HTML;
                    } elseif ($labelType === 'textarea') {
                        echo <<<HTML
                        <div class="mb-3" id="{$nameKey}_div">
                            <label for="{$nameKey}" class="form-label">
                                <b>$cleanLabel</b></label>
                            <textarea class="form-control-lg" autocomplete="new-$nameKey" data-original="$value" name="{$nameKey}" id="{$nameKey}">$value</textarea>
                            <small id="{$nameKey}_help" class="form-text text-muted"></small>
                            <small id="{$nameKey}_error" class="form-text text-danger"></small>
                        </div>

                    HTML;
                    } else {
                        echo <<<HTML
                            <div class="form-group $name" id="{$name}_div">
                            <label for="$id" class="form-label"><b>$cleanLabel</b></label>
                            <div class="input-group mb-3">
                               
                                <input type="$labelType" class="form-control is-medium" data-original="$value" value="$value" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                            </div>
                            <small id="{$name}_help" class="form-text text-muted"></small>
                            <small id="{$name}_error" class="form-text text-danger"></small>
                            </div>

                            HTML;
                    }
                }
                echo <<<HTML
                        
                        </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'mixed_nested') {
                $divID = $this->entKey[$i];
                echo <<<HTML
                              
                                      <div class="row gx-4 " id="$divID">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $label = $this->entValue[$i]['label'][$y];
                    $name = $this->entValue[$i]['attribute'][$y];
                    $nestedName = $divID . "['" . $name . "']";
                    $value = $this->entValue[$i]['value'][$y] ?? '';
                    $placeholder = $this->entValue[$i]['placeholder'][$y] ?? null;
                    $id = $name;
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $labelType = $this->entValue[$i]['inputType'][$y] ? $this->entValue[$i]['inputType'][$y] : '';
                    $icon = $this->entValue[$i]['icon'][$y] ?? '';
                    $hasIconLeft = (isset($this->entValue[$i]['icon'][$y]) ? 'has-icon-left' : '');
                    $hasImg = ($this->entValue[$i]['img'][$y] ?? '');
                    $multiple = ''; // multiple for file input

                    if ($labelType === 'select') {
                        echo <<<HTML
                                <div class="form-group $name" id="{$name}_div">
                                    <label for="$id" class="form-label"><b>$cleanLabel</b></label>
                                    <div class="input-group mb-3">
                                        
                                        <select class="form-control form-control-lg" id="$id" name="$nestedName" data-original="$value">                                                           
                            HTML;

                        if ($this->entValue[$i]['options'][$y]) {
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value) {
                                echo "<option value='$value'> $value </option>";
                            }
                        }
                        // for ($yii = 0; $yii < count($this->entValue[$i]['options'][$yii]); $yii++) {
                        //     echo "<option>" . $this->entValue[$i]['options'][$yii] . "</option>";
                        // }
                        echo <<<HTML
                                        </select>
                                      
                                        <!-- <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="fas fa-angle-down fasCol"></i>
                                            </span>
                                        </div> -->
                                        </div>
                                        <small id="$help" class="form-text text-muted"></small>
                                        <small id="$error" class="form-text text-danger"></small>
                                        </div>
                                        </div>

                            HTML;
                    } elseif ($labelType === 'inputButton') {
                        echo <<<HTML
                            
                                <div class="form-group $name" id="{$name}_div">
                                <div class="input-group mb-3">
                                    
                                    <input type="text" class="form-control is-medium" data-original="$value" value="$value" id="{$name}" name="$nestedName" placeholder="$cleanLabel">
                                </div>
                                <small id="{$name}_help" class="form-text text-muted"></small>
                                <button class="btn btn-success btn-lg btn-block" id="{$name}_button">Search</button>
                                <small id="{$name}_help" class="form-text text-muted"></small>
                                <small id="{$name}_error" class="form-text text-danger"></small>
                            </div>

                            HTML;
                    } elseif ($labelType === 'cardSelect') {
                        echo <<<HTML
                                                <div class="$name col m-1" id="{$name}_div">
                                                    <div class="card h-100 hidden">
                                                <img src="$hasImg" class="card-img-top" alt="...">
                                               
                                                    <div class="card-body">

                                                        <h5 class="card-title">$cleanLabel</h5>

                                                    
                                        
                                                    
                            HTML;
                        if ($this->entValue[$i]['options'][$y]) {
                            echo <<<HTML
                                        <select class="form-select form-select-lg mb-3" arial-label='Default' id="$id" data-original="$value" name="$nestedName">
                                            <option value='$value'> 
                                                <span class="option_text">Choose </span>
                                                </option>
                                HTML;
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value => $option) {
                                echo "<option value='$value'>
                                <span class='option_text'> $option 
                                </span> </option>";
                            }
                            echo <<<HTML
                                    </select>
                                HTML;
                        } else {
                            echo <<<HTML
                                    <input type="text" class="form-control" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                                HTML;
                        }
                        echo <<<HTML
                                                   
                                                 
                                               
                                               <small id="$help" class="form-text text-muted"></small>
                                                <small id="$error" class="form-text text-danger"></small> 
                                                </div>
                                            </div>
                                            </div>
                            HTML;
                    } elseif ($labelType === 'file') {

                        if (strpos($name, '[]') !== false) {
                            $name = str_replace(['[', ']'], '', $name);
                            $multiple = "multiple";
                        }




                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label class="label is-medium" id="$name"><b>$cleanLabel</b></label>
                                <div class="control is-expanded $hasIconLeft">
                                    <input class="input $name input is-medium" type="$labelType" value="$value" maxlength="30" minlength="1" name="$name" id="{$name}"
                                    placeholder="$placeholder" autocomplete="$name" $multiple>
                                    <span class="icon is-small is-left">$icon</span>
                                    <p class="help" id="{$name}_help"></p>
                                    <p class="help error" id="{$name}_error"></p>
                                </div>
                            </div>
                            HTML;
                    } elseif ($labelType === 'textarea') {
                        echo <<<HTML
                        <div class="mb-3" id="{$nameKey}_div">
                            <label for="{$nameKey}" class="form-label">
                                <b>$cleanLabel</b></label>
                            <textarea class="form-control-lg" autocomplete="new-$nameKey" data-original="$value" name="{$nameKey}" id="{$nameKey}">$value</textarea>
                            <small id="{$nameKey}_help" class="form-text text-muted"></small>
                            <small id="{$nameKey}_error" class="form-text text-danger"></small>
                        </div>

                    HTML;
                    } else {
                        echo <<<HTML
                            <div class="form-group $name" id="{$name}_div">
                            <label for="$id" class="form-label"><b>$cleanLabel</b></label>
                            <div class="input-group mb-3">
                               
                                <input type="$labelType" class="form-control is-medium" value="$value" maxlength="30" minlength="1" name="$nestedName" id="$id" placeholder="$placeholder" autocomplete="$name">
                            </div>
                            <small id="{$name}_help" class="form-text text-muted"></small>
                            <small id="{$name}_error" class="form-text text-danger"></small>
                            </div>

                            HTML;
                    }
                }
                echo <<<HTML
                        
                        </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'select-many') {
                $divID = $this->entKey[$i];
                echo <<<HTML
                    <div class="form-group" id="$divID">
                        <div class="row">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $options = $this->entValue[$i]['options'][$y];
                    $label = $this->entValue[$i]['label'][$y];
                    $name = $this->entValue[$i]['attribute'][$y];
                    $id = $name;
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $icon = $this->entValue[$i]['icon'][$y];
                    echo <<<HTML
                        <div class="form-group col" id="{$name}_div">
                            <label for="$id" class="form-label"><b>$cleanLabel</b></label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$icon</span>
                                </div>
                                <select class="form-control form-control-lg" id="$id" name="$name">
                        HTML;
                    foreach ($options as $option) {
                        echo "<option value=\"$option\">$option</option>";
                    }
                    echo <<<HTML
                                </select>
                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        <i class="fas fa-angle-down fasCol"></i>
                                    </span>
                                </div>
                            </div>
                            <small id="$help" class="form-text text-muted"></small>
                            <small id="$error" class="form-text text-danger"></small>
                        </div>
                        HTML;
                }
                echo <<<HTML
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'title') {
                echo <<<HTML
                    <hr><br>
                    <h1 id="{$nameKey}1" class="text-uppercase text-center text-primary">$var</h1><br>
                    HTML;
            } elseif ($this->entValue[$i] === 'subtitle') {
                echo <<<HTML
                    <h3 class="text-center text-primary">$var</h3>
                    HTML;
            } elseif ($this->entValue[$i] === 'p') {
                echo <<<HTML
                    <p class="text-center text-primary">$var</p>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'radio') {
                $labelValue = strtoupper($this->entValue[$i][1]);
                echo <<<HTML
                                <hr>


                            <div class="form-check">
                                <b class="h6">$labelValue</b>
                                <input class="form-check-input" type="radio" name="{$this->entKey[$i]}" value="{$this->entValue[$i][2]}" id="{$this->entKey[$i]}_yes">
                                <label class="form-check-label" for="{$this->entKey[$i]}_yes">
                                    {$this->entValue[$i][2]}
                                </label>
                            </div>
                         
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="{$this->entKey[$i]}" value="{$this->entValue[$i][3]}" id="{$this->entKey[$i]}_no">
                                <label class="form-check-label" for="{$this->entKey[$i]}_no">
                                    {$this->entValue[$i][3]}
                                </label>
                            </div>
                        <small id="{$this->entKey[$i]}_help" class="form-text text-muted"></small>
                        <small id="{$this->entKey[$i]}_error" class="form-text text-danger"></small>

                    HTML;
            } elseif ($this->entValue[$i][0] === 'radioComment') {
                $labelValue = strtoupper($this->entValue[$i][1]);
                echo <<<HTML
                    <hr>
                    <div class="form-group">
                        <label class="form-check-label">
                            <b class="h6">$labelValue</b>
                            <input type="radio" class="form-check-input" name="{$this->entKey[$i]}" value="{$this->entValue[$i][2]}" id="{$this->entKey[$i]}_yes"> {$this->entValue[$i][2]}
                        </label>
                        <br>
                        <label class="form-check-label">
                            <input type="radio" class="form-check-input" name="{$this->entKey[$i]}" value="{$this->entValue[$i][3]}" id="{$this->entKey[$i]}_no"> {$this->entValue[$i][3]}
                        </label>
                        <small id="{$this->entKey[$i]}_help" class="form-text text-muted"></small>
                        <small id="{$this->entKey[$i]}_error" class="form-text text-danger"></small>
                        <textarea name="{$this->entKey[$i]}Comment" id="{$this->entKey[$i]}Comment" placeholder="Please add any further relevant comment especially if you selected No" class="form-control" style="width: 110%; height: 100px; padding: 12px 20px; box-sizing: border-box; border: 2px solid #ccc; border-radius: 4px; background-color: #f8f8f8; font-size: 16px;"></textarea>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'hr') {
                echo '<hr>';
            } elseif ($this->entValue[$i] === 'br') {
                echo '<br>';
            } elseif ($this->entValue[$i] === 'loader') {
                echo "<div id='setLoader' tabindex='-1' class='loader noDisplay'></div>";
            } elseif ($this->entValue[$i] === 'setError') {
                $nameKey = $this->entKey[$i];
                echo "<div class='alert alert-danger noDisplay' id='$nameKey'><p id='error'></p></div>";
            } elseif ($this->entValue[$i] === 'showError') {
                echo "<div id='setLoader' tabindex='-1' class='loader noDisplay'></div>
    <div class='alert alert-danger noDisplay' id='$nameKey'><p id='error'></p></div>";
            } elseif ($this->entValue[$i] == 'showPassword') {
                echo <<<HTML
                       <label class="checkbox">
                        <input type="checkbox" id="showPassword">
                            Show Password
                        </label><br>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'button_captcha') {
                $js = $this->entValue[$i]['js'];
                $siteKey = $this->entValue[$i]['key'];
                $action = $this->entValue[$i]['action'];
                echo <<<HTML
                    <div class="mb-3">
                        <button 
                            type="button"
                            id="button"
                            class="btn btn-success btn-lg w-100 g-recaptcha"
                            data-sitekey="$siteKey" 
                            data-callback="$js" 
                            data-action="$action">
                            {$nameKey}
                        </button>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'file') {
                if (strpos($nameKey, '[]') !== false) {
                    $nameKey = str_replace(['[', ']'], '', $nameKey);
                    $multiple = "multiple";
                } else {
                    $multiple = '';
                    $fileName = $nameKey;
                }
                echo <<<HTML
                    <div class="mb-3" id="{$nameKey}_div">
                        <label for="$nameKey" class="form-label"><b>$var</b></label>
                        <input class="form-control" type="file" id="$nameKey" name="{$this->entKey[$i]}" $multiple>
                        <small id="{$nameKey}_help" class="form-text text-muted"></small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>
                    HTML;
            }elseif ($this->entValue[$i] === 'recaptcha') {
                $recaptcha = $_ENV['RECAPTCHA_KEY'];

                echo <<<HTML
                 
                        <div class="g-recaptcha" data-sitekey="$recaptcha" data-theme="dark"></div>
                     

                HTML;

            }elseif($this->entValue[$i] === 'dToken') {
                echo <<<HTML
                <input type="hidden" name="dToken" value= "{$this->dToken}">
                HTML;
            } else {
                echo "Invalid form element type: {$this->entValue[$i]}";
            }
        }
    }
}
