<?php

namespace App\Filament\Resources\PerangkatResource\Pages;

use App\Filament\Resources\PerangkatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerangkat extends EditRecord
{
    protected static string $resource = PerangkatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
