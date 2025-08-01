<?php

declare(strict_types=1);

namespace Src;

class FormBuilderBulma
{
    /**
     * This function is used to build a form
     * it takes an array which denotes the type of question
     * When there is a need for new entries, use the newEnt array.
     */
    private array $entKey;

    private string $token;

    private array $entValue;

    private int $entCount;

    public ?string $ref = null;

    public ?string $year = null;

    public ?string $month = null;

    private array $setYear = [];

    private array $setDay = [];

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
    }

    /**
     * it extracts out the values of the form. this is what we use to decide the type of question.
     *
     * @return array
     */
    public function setEntValue(): array
    {
        $this->entValue = array_values($this->question);
        $this->entCount = count($this->entValue);

        return $this->entValue;
    }

    /**
     * to create the year of birth
     * set the years and create an array.
     */
    private function createYear(int $startVar, int $dayOrYear): array
    {
        $setYear = [];
        for ($i = $startVar; $i <= $dayOrYear; ++$i) {
            $setYear[] = $i;
        }

        return $setYear;
    }

    private function createDay(int $startVar, int $dayOrYear): array
    {
        $setDay = [];
        for ($i = $startVar; $i < $dayOrYear; ++$i) {
            $setDay[] = $i;
        }

        return $setDay;
    }

    public function getYear(): void
    {
        $yearLimit = (int) date('Y');
        $this->setYear = $this->createYear(1930, $yearLimit);

        foreach ($this->setYear as $no) {
            echo "<option value=\"$no\">$no</option>";
        }
    }

    private function getDay(): void
    {
        $this->setDay = $this->createDay(0o1, 32);
        foreach ($this->setDay as $no) {
            echo "<option value=\"$no\">$no</option>";
        }
    }

    /**
     * function to set the key of the form. Keys are the names of questions and the names of the database.
     */
    public function setEntKey(): array
    {
        $this->entKey = array_keys($this->question);

        return $this->entKey;
    }

    public function setSessionToken(): string
    {
        $_SESSION['token'] = $this->token;

        return $_SESSION['token'];
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
     *'jobSuitability'=> [ 'radio', 'Do you have any health conditions that would prevent you from meeting these intrinsic requirements for which the company might need to make reasonable adjustments? (If yes, please be aware that we may need to discuss these with you at your interview)  ', 'Yes', 'No' ],
     textarea 'rightToWorkMoreInfo'=> ['textarea', 'if you answered yes, what document will you provide to prove this?'],
     */
    public function genForm(): void
    {
        $this->setEntValue();
        $this->setEntKey();
        $this->setSessionToken();

        for ($i = 0; $i < $this->entCount; ++$i) {
            $value = isset($_POST['button']) ? $_POST[$this->entKey[$i]] : '';

            $var = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $this->entKey[$i]));
            $nameKey = $this->entKey[$i];

            if ($this->entValue[$i] === 'text') {
                echo <<<HTML
                            <div class='form-group'>
                                <label for='{$nameKey}_id' class='form-label'><b>$var</b></label>
                                <input type='text' class='form-control' autocomplete='new-$nameKey' placeholder='PLEASE ENTER YOUR $var' name='$nameKey' id='{$nameKey}_id' value='$value' required>
                                <small id='{$nameKey}_help' class='form-text text-muted'></small>
                                <small id='{$nameKey}_error' class='form-text text-danger'></small>
                            </div>

                    HTML;
            } elseif ($this->entValue[$i][0] === 'text-icon') {
                $fontAwesome = $this->entValue[$i][1];
                echo <<<HTML
                                    <div class="form-group">
                        <label for="{$nameKey}_id" class="form-label"><b>$var</b></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    $fontAwesome
                                </span>
                            </div>
                            <input type="text" class="form-control" autocomplete="new-$nameKey" placeholder="$var" required name="$nameKey" id="{$nameKey}_id" value="$value">
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
                            <div class="form-group">
                                <label for="{$nameKey}_id" class="form-label"><b>$var</b></label>
                                <input type="date" class="form-control" id="{$nameKey}_id" autocomplete="new-$nameKey" placeholder="$var" required name="$nameKey" value="$value">
                                <small id="{$nameKey}_help" class="form-text text-muted"></small>
                                <small id="{$nameKey}_error" class="form-text text-danger"></small>
                            </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'select') {
                $options = $this->entValue[$i];
                echo <<<HTML
                    <div class="form-group">
                        <label for="{$nameKey}" class="form-label"><b>$var</b></label>
                        <select class="form-control" name="$nameKey" id="{$nameKey}">
                            <?php foreach ($options as $option): ?>
                                <option value="<?= $option ?>"><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="{$nameKey}_help" class="form-text text-muted"></small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i][0] === 'textarea') {
                echo <<<HTML
                    <div class="form-group" id="{$nameKey}_div">
                        <label for="{$nameKey}_id" class="form-label"><b>{$this->entValue[$i][1]}</b></label>
                        <textarea class="form-control" autocomplete="new-$nameKey" placeholder="{$this->entValue[$i][1]}" name="{$this->entKey[$i]}" id="{$this->entKey[$i]}_id">$value</textarea>
                        <small id="{$this->entKey[$i]}_help" class="form-text text-muted"></small>
                        <small id="{$this->entKey[$i]}_error" class="form-text text-danger"></small>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i] === 'email') {
                echo <<<HTML
                    <div class="form-group">
                        <label for="{$nameKey}_id" class="form-label"><b>$var</b></label>
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                            </div>
                            <input type="email" class="form-control $nameKey is-medium" autocomplete="username" placeholder="email" name="$nameKey" id="{$nameKey}_id" value="$value">
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="fas fa-check"></i>
                                </span>
                            </div>
                        </div>
                        <small id="{$nameKey}_help" class="form-text text-muted"></small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i] === 'password') {
                echo <<<HTML
                    <div class="form-group">
                        <label for="{$nameKey}_id" class="form-label"><b>$var</b></label>
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                            <input type="password" class="form-control $nameKey is-medium" autocomplete="new-password" placeholder="password" name="$nameKey" id="{$nameKey}_id">
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="fas fa-check"></i>
                                </span>
                            </div>
                        </div>
                        <small id="{$nameKey}_help" class="form-text text-muted"></small>
                        <small id="{$nameKey}_error" class="form-text text-danger"></small>
                    </div>

                    HTML;
            } elseif ($this->entKey[$i] === 'checkbox') {
                echo <<<HTML
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="checkbox" name="$nameKey">
                            <label class="form-check-label" for="checkbox">
                                {$this->entValue[$i]}
                            </label>
                            <small id="{$nameKey}_error" class="form-text text-danger"></small>
                        </div>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i] === 'button') {
                echo <<<HTML
                                    <br>
                    <div class="form-group">
                        <p class="d-flex justify-content-center">
                            <button name="button" id="button" type="button" class="btn btn-success btn-lg btn-block submit">
                                {$nameKey}
                            </button>
                        </p>
                    </div>

                    HTML;
            } elseif ($this->entValue[$i] === 'submit') {
                echo <<<HTML
                    <div class="field">
                        <p class="control">
                            <button name="submit" id="submit" type="submit" class="button is-success button is-large is-fullwidth submit">
                                Submit
                            </button>
                        </p>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'token') {
                echo <<<HTML
                        <div class="form-group">
                            <p class="d-flex justify-content-center">
                                 <input type="hidden" class="input" id="token" name="token" value="{$this->token}">
                            </p>
                        </div>

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
                                    <select class="form-control form-control-lg" name="day" id="day">
                                        <option selected value="select">Day</option>

                    HTML;
                echo $this->getDay();
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
                                <option value="Jan">Jan</option>
                                <option value="Feb">Feb</option>
                                <option value="Mar">Mar</option>
                                <option value="Apr">Apr</option>
                                <option value="May">May</option>
                                <option value="Jun">Jun</option>
                                <option value="Jul">Jul</option>
                                <option value="Aug">Aug</option>
                                <option value="Sep">Sep</option>
                                <option value="Oct">Oct</option>
                                <option value="Nov">Nov</option>
                                <option value="Dec">Dec</option>
                            </select>
                            <small id="month_error" class="form-text text-danger"></small>
                            <small id="month_help" class="form-text text-muted"></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-group">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-control form-control-lg" name="year" id="year">
                                <option selected value="select">Year</option>

                    HTML;
                echo $this->getYear();
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
                                  <div class="form-group" id="$divID">
                                      <div class="row">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $label = $this->entValue[$i]['label'][$y];
                    $name = $this->entValue[$i]['attribute'][$y];
                    $value = $this->entValue[$i]['value'][$y] ?? '';
                    $placeholder = $this->entValue[$i]['placeholder'][$y] ?? null;
                    $id = $name . '_id';
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $labelType = $this->entValue[$i]['inputType'][$y] ? $this->entValue[$i]['inputType'][$y] : '';
                    $icon = $this->entValue[$i]['icon'][$y] ?? '';
                    $hasIconLeft = (isset($this->entValue[$i]['icon'][$y]) ? 'has-icon-left' : '');
                    $hasImg = ($this->entValue[$i]['img'][$y] ?? '');

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
                                    
                                    <input type="text" class="form-control is-medium" id="{$name}_id" name="$name" placeholder="$cleanLabel">
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
                                            <div class="card-image">
                                                <figure class="image is-4by3">
                                                <img src="$hasImg"
                                                    alt="Placeholder image"
                                                />
                                                </figure>
                                            </div>

                                             <header class="card-header">
                                                <p class="card-header-title">>$cleanLabel</p>
                                             
                                            </header>
                                   
                                        <div class="card-content">

                                            <div class="content">

                                        
                                    
                                        
                            HTML;
                        if ($this->entValue[$i]['options'][$y]) {
                            echo <<<HTML
                                                    <select class="select is-primary" arial-label='Default' id="$id" name="$name">
                                                        
                                                        <option value='$value'> <span style="font-size: 20px;">Choose </span></option>
                                HTML;
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value=> $option) {
                                echo "<option value='$value'>
                                            <span style='font-size: 20px;'> $option 
                                            </span> </option>";
                            }
                            echo <<<HTML
                                                    </select>
                                HTML;
                        } else {
                            echo <<<HTML
                                                    <input type="text" class="input is-primary" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                                HTML;
                        }
                        echo <<<HTML
                                                   
                                                 
                                               
                                                <!-- <small id="$help" class="form-text text-muted"></small>
                                                <small id="$error" class="form-text text-danger"></small> -->
                                            </div>
                                            </div>
                                            </div>
                                             </div>
                                            </div>
                                            </div>
                            HTML;
                    } else {
                        echo <<<HTML
                            <div class="form-group $name" id="{$name}_div">
                            <label for="$id" class="form-label"><b>$cleanLabel</b></label>
                            <div class="input-group mb-3">
                               
                                <input type="$labelType" class="form-control is-medium" value="$value" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                            </div>
                            <small id="{$name}_help" class="form-text text-muted"></small>
                            <small id="{$name}_error" class="form-text text-danger"></small>
                            </div>

                            HTML;
                    }
                }
                echo <<<HTML
                        </div>
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
                    $id = $name . '_id';
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
                    <p id="{$nameKey}1" class="h3 text-center text-primary">$var</p><br>
                    HTML;
            } elseif ($this->entValue[$i] === 'subtitle') {
                echo <<<HTML
                    <h2 class="h6 text-center text-primary">$var</h2>
                    HTML;
            } elseif ($this->entValue[$i] === 'p') {
                echo <<<HTML
                    <p class="text-center text-primary">$var</p>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'radio') {
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
                    </div>
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
            } elseif ($this->entValue[$i] === 'empty') {
                echo '';
            } elseif ($this->entValue[$i] === 'showError') {
                echo "<div id='setLoader' tabindex='-1' class='loader' style='display: none';></div>
    <div class='alert alert-danger' id='$nameKey' style='display: none;'><p id='error'></p></div>";
            } elseif ($this->entValue[$i] === 'hr') {
                echo '<hr>';
            } elseif ($this->entValue[$i] === 'br') {
                echo '<br>';
            } else {
                echo "Invalid form element type: {$this->entValue[$i]}";
            }
        }
    }
}
