<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerangkatResource\Pages;
use App\Filament\Resources\PerangkatResource\RelationManagers;
use App\Models\Perangkat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PerangkatResource extends Resource
{
    protected static ?string $model = Perangkat::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('keterangan')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active?')
                    ->default(true),
                // ->required(),
                Forms\Components\TextInput::make('merk')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('nomor')
                    ->numeric()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('alamat_ip')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->default('-')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active?')
                    ->boolean()
                    ->searchable(),
                Tables\Columns\TextColumn::make('merk')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nomor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('alamat_ip')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerangkats::route('/'),
            'create' => Pages\CreatePerangkat::route('/create'),
            'edit' => Pages\EditPerangkat::route('/{record}/edit'),
        ];
    }
}
