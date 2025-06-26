<div class="mb-5">
    <div class="mb-4">
        <h2 class="h4 fw-bold text-danger">
            {{ __('Delete Account') }}
        </h2>
        <p class="text-muted">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </div>

    <button 
        type="button" 
        class="btn btn-danger"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        {{ __('Delete Account') }}
    </button>

    <div 
        x-data="{ show: false }" 
        x-show="show" 
        x-on:open-modal.window="show = true"
        x-on:close.window="show = false"
        x-transition
        style="display: none"
        class="modal fade"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <form method="post" action="{{ route('profile.destroy') }}">
                    @csrf
                    @method('delete')

                    <h2 class="h5 fw-bold mb-3">
                        {{ __('Are you sure you want to delete your account?') }}
                    </h2>

                    <p class="text-muted mb-4">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>

                    <div class="mb-4">
                        <label for="password" class="visually-hidden">{{ __('Password') }}</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                            placeholder="{{ __('Password') }}"
                        />
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-3">
                        <button 
                            type="button" 
                            class="btn btn-outline-secondary"
                            x-on:click="$dispatch('close')"
                        >
                            {{ __('Cancel') }}
                        </button>

                        <button type="submit" class="btn btn-danger">
                            {{ __('Delete Account') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>