@extends('layouts.app')

@section('title', 'Templates - WhatsApp')
@section('page-title', 'Message Templates')

@section('content')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Message Templates</h2>
            <p class="text-sm text-gray-500 mt-1">Create and manage reusable message templates</p>
        </div>
        <button onclick="openTemplateModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition flex items-center space-x-2">
            <i class="fas fa-plus"></i>
            <span>New Template</span>
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($templates as $template)
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $template->name }}</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @if($template->is_active) bg-green-100 text-green-800 @else bg-gray-100 text-gray-800 @endif">
                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="editTemplate({{ $template->id }})" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('whatsapp.templates.destroy', $template) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">{{ Str::limit($template->content, 100) }}</p>
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span><i class="fas fa-tag mr-1"></i>{{ ucfirst($template->type) }}</span>
                    <span>{{ $template->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 bg-white rounded-lg shadow-md">
                <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No templates yet</p>
                <button onclick="openTemplateModal()" class="mt-4 inline-block bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition">
                    Create Your First Template
                </button>
            </div>
        @endforelse
    </div>
</div>

<!-- Template Modal -->
<div id="templateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white">
            <h3 class="text-lg font-semibold text-gray-900">Message Template</h3>
            <button onclick="closeTemplateModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="templateForm" method="POST" class="p-6">
            @csrf
            <div id="formMethod"></div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Template Name *</label>
                    <input type="text" name="name" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                    <select name="type" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="text">Text</option>
                        <option value="media">Media</option>
                        <option value="template">WhatsApp Template</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                    <textarea name="content" rows="6" required placeholder="Enter template content. Use @{{variable}} for dynamic values." class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use @{{variable}} syntax for dynamic values (e.g., Hello @{{name}})</p>
                </div>
                
                <div id="mediaFields" class="hidden">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Media URL</label>
                        <input type="url" name="media_url" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Media Type</label>
                        <select name="media_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="document">Document</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeTemplateModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition">
                    Save Template
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openTemplateModal() {
        document.getElementById('templateModal').classList.remove('hidden');
        document.getElementById('templateForm').action = '{{ route("whatsapp.templates.store") }}';
        document.getElementById('formMethod').innerHTML = '';
        document.getElementById('templateForm').reset();
    }
    
    function closeTemplateModal() {
        document.getElementById('templateModal').classList.add('hidden');
    }
    
    function editTemplate(id) {
        // Load template data and populate form
        // For now, just open modal - you can enhance this with AJAX
        openTemplateModal();
    }
    
    document.getElementById('templateForm').querySelector('select[name="type"]').addEventListener('change', function() {
        const mediaFields = document.getElementById('mediaFields');
        if (this.value === 'media') {
            mediaFields.classList.remove('hidden');
        } else {
            mediaFields.classList.add('hidden');
        }
    });
</script>
@endpush
@endsection

