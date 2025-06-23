<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller as Controller;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\Rules\Password;

use function Pest\Laravel\json;

class AuthController extends Controller
{
    /**
     * Exibe uma lista dos recursos.
     */
    public function index()
    {
        //
    }


    /**
     * Armazena um novo recurso no armazenamento.
     */
    public function register(Request $request)
    {
        $input = $request->all(); // data

        // Remover caracteres não numéricos antes de validar
        $input['cpf_cnpj'] = preg_replace('/\D/', '', $input['cpf_cnpj'] ?? '');
        $input['telefone'] = preg_replace('/\D/', '', $input['telefone'] ?? '');

        $validated = Validator::make($input, [
            'name' => 'required|min:8|string|max:100',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => ['required' , 'string' , 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'tipo_pessoa' => ['required', 'in:pf,pj'],
                    'cpf_cnpj' => [
                        'required',
                        'string',
                        'max:20',
                        'unique:users,cpf_cnpj',
                        function ($attribute, $value, $fail) use ($input) {
                            $value = preg_replace('/\D/', '', $value); // só números
                            if (($input['tipo_pessoa'] ?? '') === 'pf' && !$this->validarCPF($value)) {
                                $fail('O CPF informado é inválido.');
                            }
                            if (($input['tipo_pessoa'] ?? '') === 'pj' && !$this->validarCNPJ($value)) {
                                $fail('O CNPJ informado é inválido.');
                            }
                        },
                ],
            'telefone' => ['required', 'string', 'regex:/^\(?\d{2}\)?[\s-]?\d{4,5}-?\d{4}$/'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
        ], [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto válido.',
            'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
            'name.required' => 'O campo Nome é obrigatório.',
            'name.min' => 'O Nome deve ter no mínimo :min caracteres.',
            'email.email' => 'O campo de e-mail precisa ser um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter no mínimo :min caracteres.',
            'password.letters' => 'A senha deve conter pelo menos uma letra.',
            'password.mixed' => 'A senha deve conter pelo menos uma letra maiúscula e minúscula.',
            'password.numbers' => 'A senha deve conter pelo menos um número.',
            'password.symbols' => 'A senha deve conter pelo menos um símbolo especial.',
            'cpf_cnpj.required' => 'O CPF ou CNPJ é obrigatório.',
            'cpf_cnpj.unique' => 'Este CPF ou CNPJ já está em uso.',
            'tipo_pessoa.required' => 'O tipo de pessoa é obrigatório.',
            'tipo_pessoa.in' => 'O tipo de pessoa deve ser pf ou pj.',
            'telefone.regex' => 'O telefone informado não é válido. Ex: (11) 91234-5678',
            'inscricao_estadual.max' => 'A inscrição estadual deve ter no máximo :max caracteres.',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validated->errors()
            ], 422);
        }
        $validated = $validated->validated(); // verificalçãao da validação

        try {
            // criar usuario
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'tipo_pessoa' => $validated['tipo_pessoa'],
                'cpf_cnpj' => $validated['cpf_cnpj'],
                'telefone' => $validated['telefone'],
                'inscricao_estadual' => $validated['inscricao_estadual'] ?? null,
            ]);


            $loginRequest = new Request([
                'email' => $validated['email'],
                'password' => $input['password'], 
            ]);

            return $this->login($loginRequest);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'msg' => 'Erro ao cadastrar.',
            ], 500);
        }

    }
    public function login(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'email' => 'required|email:rfc,dns',
            'password' => ['required' , 'string' , Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'required' => 'O campo :attribute é obrigatório.',
            'email.email' => 'O campo de e-mail precisa ser um e-mail válido.',
            'password.min' => 'A senha deve ter no mínimo :min caracteres.',
            'password.letters' => 'A senha deve conter pelo menos uma letra.',
            'password.mixed' => 'A senha deve conter pelo menos uma letra maiúscula e minúscula.',
            'password.numbers' => 'A senha deve conter pelo menos um número.',
            'password.symbols' => 'A senha deve conter pelo menos um símbolo especial.',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validated->errors()
            ], 422);
        }
        $validated = $validated->validated();

        if (Auth::attempt($validated)) {
            $user = User::where('email', $validated['email'])->firstOrFail();

            $token = $user->createToken(
                'api-token',
                ['post:read'] // lembrar de fazer a verificação caso seja admin.
            )->plainTextToken;

            return response()->json(['ok' => true, 'token' => $token]);

        }

        return response()->json(['ok' => false, 'message' => 'E-mail ou senha inválidos'], 401);

    }
    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['ok' => false, 'message' => 'Token não informado'], 400);
        }

        $access_token = PersonalAccessToken::findToken($token);
        if (!$access_token) {
            return response()->json(['ok' => false, 'message' => 'Token inválido'], 400);
        }

        // dd($access_token);
        $access_token->delete();
        return response()->json(['ok' => true, 'message' => 'Saiu do login'], 400);

    }
    /**
     * Exibe o recurso especificado.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Atualiza o recurso especificado no armazenamento.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove o recurso especificado do armazenamento.
     */
    public function destroy(string $id)
    {
        //
    }

    // FUNCOES PARA O AUTH
    public function validarCNPJ(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        $multiplicadores1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $multiplicadores2 = [6] + $multiplicadores1;

        for ($i = 0, $soma1 = 0; $i < 12; $i++) {
            $soma1 += $cnpj[$i] * $multiplicadores1[$i];
        }

        $resto1 = $soma1 % 11;
        $digito1 = $resto1 < 2 ? 0 : 11 - $resto1;

        if ($cnpj[12] != $digito1) {
            return false;
        }

        for ($i = 0, $soma2 = 0; $i < 13; $i++) {
            $soma2 += $cnpj[$i] * $multiplicadores2[$i];
        }

        $resto2 = $soma2 % 11;
        $digito2 = $resto2 < 2 ? 0 : 11 - $resto2;

        return $cnpj[13] == $digito2;
    }

    public function validarCPF(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

}
