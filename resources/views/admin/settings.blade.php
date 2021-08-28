@extends('admin.layouts.admin')

@section('title', trans('discord-auth::admin.settings.title'))

@section('content')
    <form action="{{ route('discord-auth.admin.settings') }}" method="POST">
        @csrf
        <div class="card shadow mb-4">
            <div class="card-header">
                <a class="m-0 font-weight-bold text-primary" target="_blank" rel="noopener noreferrer" href="https://discord.com/developers/applications">
                    {{ trans('discord-auth::admin.settings.discord-portal') }} (https://discord.com/developers/applications)
                </a>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label for="client_id">{{ trans('discord-auth::admin.settings.client_id') }}</label>
                    <input class="form-control" id="host" placeholder="client_id" name="client_id" value="{{ $client_id }}" required="required">
                </div>

                <div class="form-group">
                    <label for="client_secret">{{ trans('discord-auth::admin.settings.client_secret') }}</label>
                    <input class="form-control" placeholder="client_secret" type="password" id="client_secret" name="client_secret" value="{{ $client_secret }}" required="required">
                </div>

                <div class="form-group">
                    <label for="guild">{{ trans('discord-auth::admin.settings.guild') }}</label>
                    <input class="form-control" id="guild" name="guild" value="{{ $guild }}"
                           placeholder="guild">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> {{ trans('messages.actions.save') }}
                </button>
            </div>
        </div>
    </form>
@endsection
