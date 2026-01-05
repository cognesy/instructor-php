<?php

use Cognesy\Messages\Utils\File;
use Cognesy\Messages\ContentPart;

describe('File', function () {
    describe('construction', function () {
        it('creates file with base64 data', function () {
            $fileData = 'data:application/pdf;base64,JVBERi0xLjQKJdPr6eEKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwo+PgplbmRvYmoKMiAwIG9iago8PAovVHlwZSAvUGFnZXM=';
            $file = new File(fileData: $fileData, fileName: 'document.pdf', mimeType: 'application/pdf');
            
            expect($file->getBase64Bytes())->toBe($fileData);
            expect($file->getMimeType())->toBe('application/pdf');
        });

        it('creates file with file ID', function () {
            $file = new File(fileId: 'file-BK7bzQj3FfUp6VNGYLssxKcE', fileName: 'report.pdf');
            
            expect($file->getMimeType())->toBe('application/octet-stream'); // default
        });

        it('creates file with both data and ID', function () {
            $file = new File(
                fileData: 'data:text/plain;base64,SGVsbG8gV29ybGQ=',
                fileName: 'hello.txt',
                fileId: 'file-123abc',
                mimeType: 'text/plain'
            );
            
            expect($file->getBase64Bytes())->toBe('data:text/plain;base64,SGVsbG8gV29ybGQ=');
            expect($file->getMimeType())->toBe('text/plain');
        });
    });

    describe('factory methods', function () {
        it('creates from base64 with proper validation', function () {
            $base64 = 'data:application/json;base64,eyJrZXkiOiJ2YWx1ZSJ9';
            $file = File::fromBase64($base64, 'application/json');
            
            expect($file->getBase64Bytes())->toBe($base64);
            expect($file->getMimeType())->toBe('application/json');
        });

        it('throws exception for invalid base64 format', function () {
            expect(fn() => File::fromBase64('invalidbase64', 'text/plain'))
                ->toThrow(Exception::class);
        });
    });

    describe('OpenAI content part generation', function () {
        it('generates correct file content part structure with file_data', function () {
            $fileData = 'data:application/pdf;base64,JVBERi0xLjQ=';
            $file = new File(fileData: $fileData, fileName: 'document.pdf', mimeType: 'application/pdf');
            $contentPart = $file->toContentPart();
            
            expect($contentPart->type())->toBe('file');
            expect($contentPart->toArray())->toBe([
                'type' => 'file',
                'file' => [
                    'file_data' => $fileData,
                    'file_name' => 'document.pdf',
                    'file_id' => ''
                ]
            ]);
        });

        it('generates correct file content part structure with file_id', function () {
            $file = new File(fileId: 'file-BK7bzQj3FfUp6VNGYLssxKcE', fileName: 'report.pdf');
            $contentPart = $file->toContentPart();
            
            expect($contentPart->toArray())->toBe([
                'type' => 'file',
                'file' => [
                    'file_data' => '',
                    'file_name' => 'report.pdf', 
                    'file_id' => 'file-BK7bzQj3FfUp6VNGYLssxKcE'
                ]
            ]);
        });

        it('generates structure for files with both data and id', function () {
            $file = new File(
                fileData: 'data:text/csv;base64,bmFtZSxhZ2UK',
                fileName: 'data.csv',
                fileId: 'file-csv123',
                mimeType: 'text/csv'
            );
            $contentPart = $file->toContentPart();
            
            expect($contentPart->toArray()['file'])->toBe([
                'file_data' => 'data:text/csv;base64,bmFtZSxhZ2UK',
                'file_name' => 'data.csv',
                'file_id' => 'file-csv123'
            ]);
        });
    });

    describe('message generation', function () {
        it('generates OpenAI compatible message array', function () {
            $file = new File(fileId: 'file-abc123', fileName: 'document.pdf');
            $messageArray = $file->toArray();
            
            expect($messageArray['role'])->toBe('user');
            expect($messageArray['content'])->toHaveCount(1);
            expect($messageArray['content'][0])->toBeInstanceOf(\Cognesy\Messages\ContentPart::class);
            
            $contentArray = $messageArray['content'][0]->toArray();
            expect($contentArray)->toBe([
                'type' => 'file',
                'file' => [
                    'file_data' => '',
                    'file_name' => 'document.pdf',
                    'file_id' => 'file-abc123'
                ]
            ]);
        });

        it('generates message and messages objects', function () {
            $file = new File(fileData: 'data:text/plain;base64,dGVzdA==', fileName: 'test.txt');
            
            $message = $file->toMessage();
            expect($message->role()->value)->toBe('user');
            expect($message->content()->partsList()->count())->toBe(1);
            
            $messages = $file->toMessages();
            expect($messages->messageList()->count())->toBe(1);
        });
    });

    describe('OpenAI API compliance', function () {
        it('matches OpenAI file specification with file_data', function () {
            // According to OpenAI docs, file content part should have this structure:
            // {
            //   "type": "file",
            //   "file": {
            //     "file_data": "base64_encoded_file_data",  // optional
            //     "file_id": "file-abc123",                 // optional  
            //     "filename": "document.pdf"                // optional
            //   }
            // }
            
            $fileData = 'data:application/pdf;base64,JVBERi0xLjQ=';
            $file = new File(fileData: $fileData, fileName: 'test.pdf');
            $structure = $file->toContentPart()->toArray();
            
            expect($structure)->toHaveKey('type', 'file');
            expect($structure)->toHaveKey('file');
            expect($structure['file'])->toHaveKey('file_data');
            expect($structure['file'])->toHaveKey('file_name');
            expect($structure['file'])->toHaveKey('file_id');
            expect($structure['file']['file_data'])->toStartWith('data:');
        });

        it('matches OpenAI file specification with file_id', function () {
            $file = new File(fileId: 'file-BK7bzQj3FfUp6VNGYLssxKcE', fileName: 'uploaded.pdf');
            $structure = $file->toContentPart()->toArray();
            
            expect($structure['file']['file_id'])->toStartWith('file-');
            expect($structure['file']['file_name'])->toBe('uploaded.pdf');
        });

        it('produces valid structure for Chat Completions API', function () {
            $file = new File(
                fileData: 'data:application/json;base64,eyJ0ZXN0IjoidmFsdWUifQ==',
                fileName: 'config.json'
            );
            
            // Verify it can be used in a multimodal message
            $textPart = ['type' => 'text', 'text' => 'Please analyze this file'];
            $filePart = $file->toContentPart()->toArray();
            
            $multimodalContent = [$textPart, $filePart];
            
            expect($multimodalContent[1]['type'])->toBe('file');
            expect($multimodalContent[1]['file']['file_data'])->toContain('base64,');
            expect($multimodalContent[1]['file']['file_name'])->toBe('config.json');
        });
    });

    describe('file type handling', function () {
        it('handles various file types correctly', function () {
            $fileTypes = [
                'application/pdf' => 'document.pdf',
                'application/json' => 'data.json',
                'text/plain' => 'readme.txt',
                'text/csv' => 'data.csv',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'spreadsheet.xlsx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document.docx'
            ];
            
            foreach ($fileTypes as $mimeType => $fileName) {
                $file = new File(
                    fileData: "data:{$mimeType};base64,dGVzdA==",
                    fileName: $fileName,
                    mimeType: $mimeType
                );
                
                expect($file->getMimeType())->toBe($mimeType);
                expect($file->toContentPart()->type())->toBe('file');
            }
        });

        it('uses default mime type when none provided', function () {
            $file = new File(fileName: 'unknown.bin');
            expect($file->getMimeType())->toBe('application/octet-stream');
        });
    });

    describe('edge cases', function () {
        it('handles empty file data', function () {
            $file = new File(fileData: '', fileName: 'empty.txt');
            $contentPart = $file->toContentPart();
            
            expect($contentPart->toArray()['file']['file_data'])->toBe('');
        });

        it('handles missing filename gracefully', function () {
            $file = new File(fileId: 'file-123');
            $contentPart = $file->toContentPart();
            
            expect($contentPart->toArray()['file']['file_name'])->toBe('');
        });

        it('handles files with only file_id', function () {
            $file = new File(fileId: 'file-uploaded-123');
            $structure = $file->toContentPart()->toArray();
            
            expect($structure['file']['file_id'])->toBe('file-uploaded-123');
            expect($structure['file']['file_data'])->toBe('');
        });
    });
});
