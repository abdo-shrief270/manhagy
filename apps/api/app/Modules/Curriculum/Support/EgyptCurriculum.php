<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Support;

use App\Support\Text\SearchText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The starting tree for Egypt: stages, grades, tracks and the usual subjects before university,
 * and the public universities. A starting point the platform admins review and complete (the
 * ministry changes subjects often; faculties, courses, units and lessons come from teachers'
 * suggestions). Adds only what is missing, so it can run again.
 */
final class EgyptCurriculum
{
    private const PRIMARY_LOW = ['لغة عربية', 'رياضيات', 'English', 'اكتشف', 'تربية دينية'];

    private const PRIMARY_HIGH = ['لغة عربية', 'رياضيات', 'English', 'علوم', 'دراسات اجتماعية', 'تكنولوجيا المعلومات والاتصالات', 'تربية دينية'];

    private const PREP = ['لغة عربية', 'رياضيات', 'English', 'علوم', 'دراسات اجتماعية', 'لغة فرنسية', 'تكنولوجيا المعلومات والاتصالات', 'تربية دينية'];

    /** @return array<string, array<string, list<string>|array<string, list<string>>>> stage => grade => subjects | track => subjects */
    public static function schools(): array
    {
        $primary = [];
        foreach (['الأول', 'التاني', 'التالت', 'الرابع', 'الخامس', 'السادس'] as $i => $ordinal) {
            $primary["الصف {$ordinal} الابتدائي"] = $i < 3 ? self::PRIMARY_LOW : self::PRIMARY_HIGH;
        }

        return [
            'ابتدائي' => $primary,
            'إعدادي' => [
                'الصف الأول الإعدادي' => self::PREP,
                'الصف التاني الإعدادي' => self::PREP,
                'الصف التالت الإعدادي' => self::PREP,
            ],
            'ثانوي عام' => [
                'الصف الأول الثانوي' => ['لغة عربية', 'English', 'لغة أجنبية تانية', 'رياضيات', 'علوم متكاملة', 'تاريخ', 'فلسفة ومنطق', 'تربية دينية'],
                'الصف التاني الثانوي' => [
                    'علمي' => ['لغة عربية', 'English', 'لغة أجنبية تانية', 'رياضيات', 'فيزياء', 'كيمياء', 'أحياء'],
                    'أدبي' => ['لغة عربية', 'English', 'لغة أجنبية تانية', 'تاريخ', 'جغرافيا', 'رياضيات', 'علم نفس واجتماع'],
                ],
                'الصف التالت الثانوي' => [
                    'علمي علوم' => ['لغة عربية', 'English', 'فيزياء', 'كيمياء', 'أحياء', 'جيولوجيا'],
                    'علمي رياضة' => ['لغة عربية', 'English', 'فيزياء', 'كيمياء', 'رياضيات'],
                    'أدبي' => ['لغة عربية', 'English', 'تاريخ', 'جغرافيا', 'إحصاء'],
                ],
            ],
            'ابتدائي أزهري' => array_fill_keys(['الصف الأول الابتدائي الأزهري', 'الصف التاني الابتدائي الأزهري', 'الصف التالت الابتدائي الأزهري', 'الصف الرابع الابتدائي الأزهري', 'الصف الخامس الابتدائي الأزهري', 'الصف السادس الابتدائي الأزهري'], []),
            'إعدادي أزهري' => array_fill_keys(['الصف الأول الإعدادي الأزهري', 'الصف التاني الإعدادي الأزهري', 'الصف التالت الإعدادي الأزهري'], []),
            'ثانوي أزهري' => array_fill_keys(['الصف الأول الثانوي الأزهري', 'الصف التاني الثانوي الأزهري', 'الصف التالت الثانوي الأزهري'], []),
        ];
    }

    /** @return list<string> */
    public static function universities(): array
    {
        return [
            'جامعة القاهرة', 'جامعة عين شمس', 'جامعة الإسكندرية', 'جامعة الأزهر', 'جامعة حلوان', 'جامعة المنصورة',
            'جامعة طنطا', 'جامعة الزقازيق', 'جامعة أسيوط', 'جامعة المنيا', 'جامعة بنها', 'جامعة المنوفية',
            'جامعة كفر الشيخ', 'جامعة سوهاج', 'جامعة بني سويف', 'جامعة الفيوم', 'جامعة جنوب الوادي',
            'جامعة قناة السويس', 'جامعة دمياط', 'جامعة بورسعيد',
        ];
    }

    /** @return array<string, list<string>> skills fields => skills (trainers' courses, for anyone) */
    public static function skills(): array
    {
        return [
            'برمجة وتطوير' => ['تطوير مواقع', 'Python', 'تطبيقات موبايل', 'قواعد بيانات', 'ذكاء اصطناعي وتحليل بيانات', 'أساسيات البرمجة للأطفال'],
            'تصميم ومونتاج' => ['جرافيك ديزاين', 'UI / UX', 'مونتاج فيديو', 'تصوير', 'تصميم داخلي وأوتوكاد'],
            'تسويق وبيزنس' => ['تسويق إلكتروني', 'إعلانات السوشيال ميديا', 'كتابة محتوى', 'مبيعات وخدمة عملاء', 'ريادة أعمال', 'إدارة مشروعات'],
            'محاسبة ومكتب' => ['محاسبة', 'Excel', 'ICDL ومهارات الكمبيوتر', 'ضرائب ومحاسبة مالية', 'موارد بشرية'],
            'لغات' => ['إنجليزي محادثة', 'IELTS / TOEFL', 'ألماني', 'فرنساوي', 'إيطالي', 'لغة عربية لغير الناطقين بها'],
            'قرآن وعلوم شرعية' => ['تحفيظ القرآن', 'تجويد', 'فقه وعلوم شرعية'],
            'هوايات ومهارات' => ['رسم', 'موسيقى', 'خط عربي', 'تنمية بشرية ومهارات شخصية', 'طبخ'],
        ];
    }

    /** @return int how many skills fields and skills were added */
    public static function seedSkills(): int
    {
        $added = 0;
        $f = 0;
        foreach (self::skills() as $field => $skills) {
            $fieldId = self::ensure(null, 'field', $field, 200 + $f++, $added);
            foreach ($skills as $s => $skill) {
                self::ensure($fieldId, 'skill', $skill, $s, $added);
            }
        }

        return $added;
    }

    /** @return int how many nodes were added */
    public static function seed(): int
    {
        $added = 0;
        $sort = 0;
        foreach (self::schools() as $stage => $grades) {
            $stageId = self::ensure(null, 'stage', $stage, $sort++, $added);
            $g = 0;
            foreach ($grades as $grade => $content) {
                $gradeId = self::ensure($stageId, 'grade', $grade, $g++, $added);
                $tracks = array_is_list($content) ? ['' => $content] : $content;
                $t = 0;
                foreach ($tracks as $track => $subjects) {
                    $parent = $track === '' ? $gradeId : self::ensure($gradeId, 'track', $track, $t++, $added);
                    foreach ($subjects as $s => $subject) {
                        self::ensure($parent, 'subject', $subject, $s, $added);
                    }
                }
            }
        }
        foreach (self::universities() as $u => $university) {
            self::ensure(null, 'university', $university, 100 + $u, $added);
        }

        return $added;
    }

    private static function ensure(?string $parentId, string $kind, string $name, int $sort, int &$added): string
    {
        $search = SearchText::normalize($name);
        $id = DB::table('curriculum_nodes')
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $parentId))
            ->where('search_name', $search)->value('id');
        if ($id !== null) {
            return $id;
        }
        $id = (string) Str::uuid7();
        DB::table('curriculum_nodes')->insert([
            'id' => $id, 'parent_id' => $parentId, 'kind' => $kind, 'name' => $name, 'search_name' => $search,
            'status' => 'published', 'sort' => $sort, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $added++;

        return $id;
    }
}
