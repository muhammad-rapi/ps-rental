<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiResource\Pages;
use App\Models\Perangkat;
use App\Models\Produk;
use App\Models\Transaksi;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('transaksiItems')
                    ->relationship()
                    ->label('Item Transaksi')
                    ->schema([
                        Select::make('item_id')
                            ->label('Produk')
                            ->options(Produk::all()->pluck('nama', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $produk = Produk::find($get('item_id'));
                                if ($produk) {
                                    $set('harga_satuan', $produk->harga);
                                    $subtotal = $produk->harga * (int) $get('jumlah');
                                    $set('subtotal', $subtotal);
                                }
                                self::updateTotals($set, $get);
                            }),

                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $harga_satuan = $get('harga_satuan');
                                if ($harga_satuan) {
                                    $subtotal = $harga_satuan * (int) $get('jumlah');
                                    $set('subtotal', $subtotal);
                                }
                                self::updateTotals($set, $get);
                            }),

                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->disabled()
                            ->default(0)
                            ->dehydrated(true)
                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->defaultItems(1)
                    ->createItemButtonLabel('Tambah Produk')
                    ->live()
                    ->afterStateUpdated(fn(Set $set, Get $get) => self::updateTotals($set, $get))
                    ->deleteAction(
                        fn(Forms\Components\Actions\Action $action) => $action->after(fn(Set $set, Get $get) => self::updateTotals($set, $get)),
                    ),

                Select::make('status')
                    ->options([
                        'waiting' => 'Menunggu',
                        'completed' => 'Selesai',
                    ])
                    ->default('waiting')
                    ->required(),

                Forms\Components\TextInput::make('keterangan')
                    ->maxLength(255),

                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->required()
                    ->numeric()
                    ->disabled()
                    ->default(0)
                    ->dehydrated(true)
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->prefix('Rp'),
                
                Forms\Components\Select::make('perangkat_id')
                    ->label('Perangkat')
                    ->options(Perangkat::all()->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function updateTotals(Set $set, Get $get): void
    {
        $transaksiItems = collect($get('transaksiItems') ?? []);
        $total = 0;

        $updatedItems = $transaksiItems->map(function ($item, $index) use ($set) {
            $produk = Produk::find($item['item_id'] ?? null);
            $subtotal = 0;

            $harga_satuan = $item['harga_satuan'] ?? ($produk ? $produk->harga : 0);

            if ($harga_satuan && !empty($item['jumlah'])) {
                $subtotal = $harga_satuan * (int) $item['jumlah'];
            }

            $set("transaksiItems.{$index}.subtotal", $subtotal);

            return [
                ...$item,
                'subtotal' => $subtotal
            ];
        });

        $total = $updatedItems->sum('subtotal');
        $set('total', $total);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaksiItems')
                    ->label('Produk')
                    ->getStateUsing(function (Transaksi $record) {
                        $items = $record->transaksiItems->map(function ($item) {
                            $produk = $item->item;
                            return $produk ? "{$produk->nama} ({$item->jumlah}x)" : '-';
                        })->join(', ');
                        return $items ?: '-';
                    })
                    ->wrap()
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'waiting' => 'warning',
                        'paused' => 'warning',
                        'running' => 'primary',
                        'completed' => 'success',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Admin')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('perangkat.nama')
                    ->sortable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'running' => 'Berjalan',
                        'paused' => 'Dijeda',
                        'waiting' => 'Menunggu',
                        'completed' => 'Selesai',
                    ])
                    ->default('completed'),
                Tables\Filters\SelectFilter::make('perangkat_id')
                    ->label('Perangkat')
                    ->relationship('perangkat', 'nama'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->disabled(
                    fn(Transaksi $record): bool => $record->status === 'completed',
                ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransaksis::route('/'),
            'create' => Pages\CreateTransaksi::route('/create'),
            'edit' => Pages\EditTransaksi::route('/{record}/edit'),
        ];
    }
}
