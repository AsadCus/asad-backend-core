<?php

namespace App\Services;

class DocumentationService
{
    /**
     * @return array<string, mixed>
     */
    public function getIndexData(): array
    {
        $baseData = DocumentationIndexData::getBaseData();

        return [
            'manual' => $baseData['manual'],
            'introduction' => $baseData['introduction'],
            'bibliography' => $baseData['bibliography'],
            'roleGuide' => $baseData['roleGuide'],
            'menuStructure' => $baseData['menuStructure'],
            'modulePlaybooks' => $this->getModulePlaybooks(),
            'menuGroups' => $baseData['menuGroups'],
            'coreWorkflows' => $baseData['coreWorkflows'],
            'howToGuides' => $baseData['howToGuides'],
            'commonStatuses' => $baseData['commonStatuses'],
            'tips' => $baseData['tips'],
        ];
    }

    /**
     * Module Playbooks â€” All 18 modules from PM Manual Register.
     * Data is stored in DocumentationModulePlaybooks for maintainability.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getModulePlaybooks(): array
    {
        return DocumentationModulePlaybooks::all();
    }
}

