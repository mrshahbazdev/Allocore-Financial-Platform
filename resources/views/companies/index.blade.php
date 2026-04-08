@extends('layouts.app')
@section('title', 'Unternehmen — Allocore')
@section('page-title', '🏢 Unternehmen')
@section('topbar-actions')
    <a href="{{ route('companies.create') }}" class="btn btn-primary btn-sm">+ Unternehmen anlegen</a>
@endsection
@section('content')
<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:16px;">
    @forelse($companies as $c)
    <div class="card" style="transition:border-color .2s;" onmouseover="this.style.borderColor='rgba(99,102,241,0.4)'" onmouseout="this.style.borderColor='rgba(99,102,241,0.15)'">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:12px;">
            <div>
                <div style="font-size:16px; font-weight:600; color:#e2e8f0;">{{ $c->name }}</div>
                <div style="font-size:12px; color:#64748b; margin-top:2px;">{{ $c->industry ?? 'Keine Branche' }}</div>
            </div>
            <span class="badge badge-gray">{{ $c->currency ?? 'EUR' }}</span>
        </div>
        <div style="font-size:13px; color:#475569; margin-bottom:16px;">
            {{ $c->analyses_count }} {{ $c->analyses_count === 1 ? 'Analyse' : 'Analysen' }}
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('companies.show', $c) }}" class="btn btn-secondary btn-sm" style="flex:1; justify-content:center;">Ansehen →</a>
            <a href="{{ route('companies.edit', $c) }}" class="btn btn-secondary btn-sm">✏</a>
            <form method="POST" action="{{ route('companies.destroy', $c) }}" onsubmit="return confirm('Löschen?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm">🗑</button>
            </form>
        </div>
    </div>
    @empty
    <div class="card" style="grid-column:1/-1; text-align:center; padding:60px;">
        <div style="font-size:40px; margin-bottom:12px;">🏢</div>
        <div style="font-size:16px; font-weight:600; color:#c7d2fe; margin-bottom:8px;">Noch keine Unternehmen</div>
        <a href="{{ route('companies.create') }}" class="btn btn-primary">+ Erstes Unternehmen anlegen</a>
    </div>
    @endforelse
</div>
@endsection
