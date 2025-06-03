<?php

namespace Src\Sanitise;

use Src\AllFunctionalities;


class Sanitise extends AllFunctionalities
{
    public array $error = [];
    public array $cleanData;

    public function __construct(private array $formData, private ?array $dataLength = null)
    {
        // try {
        unset($this->formData['submit']);

        if (isset($this->formData['token'])) {
            unset($this->formData['token']);
        }
        // // } catch (\Throwable $th) {
        //     $this->error[] = "Are you human or robot;
        // }
    }

    private function validateEmail(): static
    {
        if (isset($this->formData['email']) && !filter_var($this->formData['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error[] = "Invalid Email Format";
        }
        return $this;
    }

    private function validatePassword(): static
    {
        if (isset($this->formData['password']) && isset($this->formData['confirm_password']) && $this->formData['password'] !== $this->formData['confirm_password']) {
            $this->error[] = "Your passwords do not match";
        }
        return $this;
    }

    private function checkEmpty(): static
    {
        foreach ($this->formData as $key => $value) {
            if (empty($value) && ($value == "" || $value == 'select')) {
                $cleanNameKey = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $key));
                $this->error[] = "The $cleanNameKey question is required";
            }
        }
        return $this;
    }

    private function checkLength(): static
    {
        if ($this->dataLength) {
            foreach ($this->dataLength['data'] as $index => $dataKey) {
                $dataPost = $_POST[$dataKey];
                $cleanNameKey = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $dataKey));

                if (strlen($dataPost) < $this->dataLength['min'][$index]) {
                    $this->error[] = "Your response to '{$cleanNameKey}' question does not meet the required minimum input limit";
                } elseif (strlen($dataPost) > $this->dataLength['max'][$index]) {
                    $this->error[] = "Your response to '{$cleanNameKey}' question exceeds the required maximum limit";
                }
            }
        }
        return $this;
    }

    private function sanitizeData(): static
    {
        foreach ($this->formData as $key => $value) {
            $this->formData[$key] = htmlspecialchars(strip_tags(trim(stripslashes($value))));
        }
        return $this;
    }

    private function setArrayData(): array
    {
        $options = ['cost' => 10];
        $this->cleanData = $this->formData;

        if (isset($this->cleanData['password']) && isset($this->cleanData['confirm_password'])) {
            $this->cleanData['password'] = password_hash($this->cleanData['password'], PASSWORD_DEFAULT, $options) ?? null;
            unset($this->cleanData['confirm_password']);
        }

        return $this->cleanData;
    }

    private function runValidation(): void
    {
        $this->validateEmail()->validatePassword()->checkEmpty()->checkLength()->sanitizeData();

        $this->cleanData = $this->setArrayData();
    }

    public function getCleanData(): array
    {
        $this->runValidation();
        return $this->cleanData;
    }
}
