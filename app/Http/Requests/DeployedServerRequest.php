<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\DeployedServer;
use Illuminate\Validation\Rule;



class DeployedServerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
'server_ip' => [
                'required',
                'ip',
                Rule::unique('deployed_servers')->ignore($this->deployed_server),
            ],
            'server_control_panel' => [
                'required',
                Rule::in(array_keys(DeployedServer::CONTROL_PANEL)),
            ],
            'server_region_mapping' => [
                'required',
                Rule::in(array_keys(DeployedServer::REGIONS)),
            ],
            'attached_plan' => [
                'required',
                Rule::in(array_keys(DeployedServer::PLANS)),
            ],
            'is_default' => [
                'boolean',
                Rule::unique('deployed_servers')
                    ->where(function ($query) {
                        return $query->where('owner_email', $this->owner_email)
                            ->where('is_default', true);
                    })
                    ->ignore($this->deployed_server)
                    ->when($this->is_default, function ($query) {
                        return $query;
                    }),
            ],
            'hostname' => [
                'required',
                'string',
                'max:255',
            ],
            'owner_email' => [
                'required',
                'email',
                Rule::unique('deployed_servers')
                    ->ignore($this->deployed_server)
                    ->where(function ($query) {
                        return $query->where('attached_plan', $this->attached_plan)
                            ->where('server_region_mapping', $this->server_region_mapping);
                    }),
            ],
            'serveravatar_server_id' => [
                'required',
                'integer',
                Rule::unique('deployed_servers', 'serveravatar_server_id')
                    ->ignore($this->deployed_server),
            ],



        ];
    }


 /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'server_ip' => 'Server IP',
            'server_control_panel' => 'Control Panel',
            'server_region_mapping' => 'Server Region',
            'attached_plan' => 'Plan',
            'is_default' => 'Default Server',
            'hostname' => 'Hostname',
            'owner_email' => 'Owner Email',
            'serveravatar_server_id' => 'CP Server ID',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'server_ip.unique' => 'This Server IP is already in use.',
            'server_control_panel.in' => 'The selected Control Panel is invalid.',
            'server_region_mapping.in' => 'The selected Server Region is invalid.',
            'attached_plan.in' => 'The selected Plan is invalid.',
            'is_default.unique' => 'Another server is already set as default for this owner.',
            'owner_email.unique' => 'A server with this combination of Plan, Region, and Owner Email already exists.',
            'serveravatar_server_id.unique' => 'This CP Server ID is already in use.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default' => $this->is_default ?? false,
        ]);
    }







}
