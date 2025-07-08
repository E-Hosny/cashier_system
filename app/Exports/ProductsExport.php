<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductsExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $categories = \App\Models\Category::with(['products' => function($query) {
            $query->where('type', 'finished')->with('ingredients');
        }])->get();

        $sheets = [];
        $sheets[] = new SummarySheet($categories);
        foreach ($categories as $category) {
            if ($category->products->count() > 0) {
                $sheets[] = new CategorySheet($category);
            }
        }
        return $sheets;
    }
}

class SummarySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $categories;
    protected $maxSizes = 3; // صغير، وسط، كبير

    public function __construct($categories)
    {
        $this->categories = $categories;
    }

    public function collection()
    {
        $data = collect();
        foreach ($this->categories as $category) {
            if ($category->products->count() > 0) {
                // عنوان الفئة
                $data->push([
                    'category' => $category->name,
                    'product' => '',
                    'sizes' => ['', '', ''],
                    'prices' => ['', '', ''],
                    'ingredients' => ''
                ]);
                foreach ($category->products as $product) {
                    $sizes = collect($product->size_variants ?? []);
                    $sizeNames = ['small' => 'صغير', 'medium' => 'وسط', 'large' => 'كبير'];
                    $sizesArr = [];
                    $pricesArr = [];
                    for ($i = 0; $i < $this->maxSizes; $i++) {
                        $variant = $sizes->get($i);
                        if ($variant) {
                            $sizesArr[] = $sizeNames[$variant['size']] ?? $variant['size'];
                            $pricesArr[] = $variant['price'];
                        } else {
                            $sizesArr[] = '';
                            $pricesArr[] = '';
                        }
                    }
                    $ingredients = $product->ingredients->groupBy('pivot.size')->map(function($ings, $size) use ($sizeNames) {
                        $sizeName = $sizeNames[$size] ?? $size;
                        return $sizeName . ': ' . $ings->pluck('name')->implode(', ');
                    })->implode(' | ');
                    $data->push([
                        'category' => '',
                        'product' => $product->name,
                        'sizes' => $sizesArr,
                        'prices' => $pricesArr,
                        'ingredients' => $ingredients
                    ]);
                }
                // سطر فارغ
                $data->push([
                    'category' => '',
                    'product' => '',
                    'sizes' => ['', '', ''],
                    'prices' => ['', '', ''],
                    'ingredients' => ''
                ]);
            }
        }
        return $data;
    }

    public function headings(): array
    {
        return [
            'الفئة',
            'اسم المنتج',
            'الحجم 1', 'السعر 1',
            'الحجم 2', 'السعر 2',
            'الحجم 3', 'السعر 3',
            'المكونات',
        ];
    }

    public function map($row): array
    {
        return [
            $row['category'],
            $row['product'],
            $row['sizes'][0] ?? '', $row['prices'][0] ?? '',
            $row['sizes'][1] ?? '', $row['prices'][1] ?? '',
            $row['sizes'][2] ?? '', $row['prices'][2] ?? '',
            $row['ingredients']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [ 'bold' => true, 'color' => ['rgb' => 'FFFFFF'] ],
            'fill' => [ 'fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB'] ],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER ],
        ]);
        // تلوين عناوين الفئات
        $row = 2;
        foreach ($this->categories as $category) {
            if ($category->products->count() > 0) {
                $sheet->getStyle("A$row")->applyFromArray([
                    'font' => [ 'bold' => true, 'color' => ['rgb' => '059669'] ],
                    'fill' => [ 'fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5'] ],
                ]);
                $row += $category->products->count() + 2;
            }
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, 'B' => 30, 'C' => 12, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12, 'H' => 12, 'I' => 50
        ];
    }

    public function title(): string
    {
        return 'ملخص المنتجات';
    }
}

class CategorySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $category;
    protected $maxSizes = 3;

    public function __construct($category)
    {
        $this->category = $category;
    }

    public function collection()
    {
        $data = collect();
        foreach ($this->category->products as $product) {
            $sizes = collect($product->size_variants ?? []);
            $sizeNames = ['small' => 'صغير', 'medium' => 'وسط', 'large' => 'كبير'];
            $sizesArr = [];
            $pricesArr = [];
            for ($i = 0; $i < $this->maxSizes; $i++) {
                $variant = $sizes->get($i);
                if ($variant) {
                    $sizesArr[] = $sizeNames[$variant['size']] ?? $variant['size'];
                    $pricesArr[] = $variant['price'];
                } else {
                    $sizesArr[] = '';
                    $pricesArr[] = '';
                }
            }
            $ingredients = $product->ingredients->groupBy('pivot.size')->map(function($ings, $size) use ($sizeNames) {
                $sizeName = $sizeNames[$size] ?? $size;
                return $sizeName . ': ' . $ings->pluck('name')->implode(', ');
            })->implode(' | ');
            $data->push([
                'product' => $product->name,
                'sizes' => $sizesArr,
                'prices' => $pricesArr,
                'ingredients' => $ingredients,
                'quantity' => $product->quantity ?? 'غير محدد'
            ]);
        }
        return $data;
    }

    public function headings(): array
    {
        return [
            'اسم المنتج',
            'الحجم 1', 'السعر 1',
            'الحجم 2', 'السعر 2',
            'الحجم 3', 'السعر 3',
            'المكونات',
            'الكمية المتوفرة',
        ];
    }

    public function map($row): array
    {
        return [
            $row['product'],
            $row['sizes'][0] ?? '', $row['prices'][0] ?? '',
            $row['sizes'][1] ?? '', $row['prices'][1] ?? '',
            $row['sizes'][2] ?? '', $row['prices'][2] ?? '',
            $row['ingredients'],
            $row['quantity']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [ 'bold' => true, 'color' => ['rgb' => 'FFFFFF'] ],
            'fill' => [ 'fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669'] ],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER ],
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, 'B' => 12, 'C' => 12, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12, 'H' => 50, 'I' => 20
        ];
    }

    public function title(): string
    {
        return $this->category->name;
    }
}
