<?php

namespace App\Services\Schola;

use App\Jobs\ExtractTeachingDocumentJob;
use App\Models\Student;
use App\Models\TeachingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Creazione di un materiale grezzo (TeachingDocument) dall'upload: validazione per
 * tipo sorgente, salvataggio su disco privato, dispatch dell'estrazione. Condiviso
 * tra l'upload del docente (Docente\TeachingDocumentController) e quello di scuola
 * (Scuola\SchoolMaterialController). NON gestisce il gate DPA né i redirect: quelli
 * restano nei controller (messaggi/destinazioni contestuali).
 */
class TeachingDocumentUploader
{
    /**
     * @param  array{title:string,source_type:string,subject_id?:?string,lesson_id?:?string,
     *               school_id?:?string,is_school_material?:bool,share_scope?:?string,
     *               tags?:?string}  $data
     */
    public function handle(Request $request, Student $owner, array $data): TeachingDocument
    {
        $this->validateSource($request, $data['source_type']);

        $doc = TeachingDocument::create([
            'teacher_id' => $owner->id,
            'title' => $data['title'],
            'source_type' => $data['source_type'],
            'subject_id' => $data['subject_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'school_id' => $data['school_id'] ?? $owner->school_id,
            'is_school_material' => $data['is_school_material'] ?? false,
            'share_scope' => $data['share_scope'] ?? null,
            'tags' => $this->parseTags($data['tags'] ?? null),
            'status' => 'pending',
        ]);

        [$sourceFiles, $sourceUrl] = $this->storeSource($request, $doc);
        $doc->update(['source_files' => $sourceFiles ?: null, 'source_url' => $sourceUrl]);

        ExtractTeachingDocumentJob::dispatch($doc->id)->afterResponse();

        return $doc;
    }

    /** Validazioni specifiche per tipo sorgente (identiche all'upload docente). */
    private function validateSource(Request $request, string $sourceType): void
    {
        match ($sourceType) {
            'audio' => $request->validate([
                // Audio E video: si trascrive la traccia audio. m4a = contenitore MP4
                // (PHP lo rileva audio/mp4 o video/mp4): estensione + mime espliciti.
                'file' => [
                    'required', 'file', 'max:204800',
                    'extensions:mp3,m4a,wav,ogg,mp4,mov,mpeg,avi,webm',
                    'mimetypes:'
                        . 'audio/mpeg,audio/mp3,audio/x-mp3,'
                        . 'audio/mp4,audio/x-m4a,audio/m4a,'
                        . 'audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,'
                        . 'audio/ogg,application/ogg,video/ogg,'
                        . 'audio/webm,'
                        . 'video/mp4,video/quicktime,video/mpeg,video/x-msvideo,video/avi,video/msvideo,video/webm',
                ],
            ], [], ['file' => 'file audio o video']),
            'pdf' => $request->validate([
                'file' => 'required|file|mimes:pdf|max:51200', // 50 MB
            ]),
            'docx' => $request->validate([
                'file' => 'required|file|mimes:docx,doc|max:51200',
            ]),
            'photos' => $request->validate([
                'photos' => 'required|array|min:1|max:20',
                'photos.*' => 'image|mimes:jpg,jpeg,png|max:10240', // 10 MB/foto
            ], [], ['photos' => 'foto']),
            'youtube' => $request->validate([
                'source_url' => ['required', 'url', 'max:500', 'regex:#(youtube\.com|youtu\.be)#i'],
            ], ['source_url.regex' => 'Inserisci un URL YouTube valido.']),
            'text' => $request->validate([
                'text_content' => 'required|string',
            ]),
        };
    }

    /**
     * Salva i sorgenti su disco privato. @return array{0: array<string>, 1: ?string}
     */
    private function storeSource(Request $request, TeachingDocument $doc): array
    {
        $dir = "teaching-documents/{$doc->teacher_id}/{$doc->id}";
        $sourceFiles = [];
        $sourceUrl = null;

        switch ($doc->source_type) {
            case 'audio':
            case 'pdf':
            case 'docx':
                $ext = $request->file('file')->getClientOriginalExtension() ?: $doc->source_type;
                $sourceFiles[] = $request->file('file')->storeAs($dir, "source.{$ext}", 'local');
                break;

            case 'photos':
                foreach (array_values($request->file('photos')) as $i => $photo) {
                    $ext = $photo->getClientOriginalExtension() ?: 'jpg';
                    $name = sprintf('photo_%02d.%s', $i, $ext); // ordine preservato dall'invio
                    $sourceFiles[] = $photo->storeAs($dir, $name, 'local');
                }
                break;

            case 'youtube':
                $sourceUrl = $request->input('source_url');
                break;

            case 'text':
                $path = "{$dir}/source.md";
                Storage::disk('local')->put($path, $request->input('text_content'));
                $sourceFiles[] = $path;
                break;
        }

        return [$sourceFiles, $sourceUrl];
    }

    /** @return array<string>|null */
    private function parseTags(?string $raw): ?array
    {
        if (!$raw) {
            return null;
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $tags ?: null;
    }
}
