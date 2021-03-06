<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
class AuthController extends Controller
{

    // 유저 정보 조회
    public function getUserInfo() {
        $user = Auth::user();
        if (Auth::check() && $user) {
            return response()->json([
                'data' => $user,
                'message' => '유저 정보 조회를 성공했습니다.'
            ], 200);
        } else {
            return response()->json([
                'status' => 'Unauthorized',
                'message' => '로그인이 필요합니다.'
            ], 403);
        }
    }


    // 유저 정보 변경
    public function updateUserInfo(Request $request) {
        // 유저
        $user = User::find(Auth::user()-> id);

        $valid = validator($request->only('name'), [
            'name' => 'string',
        ]);

        if ($valid->fails()) {
            return response()->json([
                'message' => '이름은 필수 입력 사항입니다.'
            ], 500);
        }

        // 이미지
        if ($request->hasFile('avatar_img')) {
            $avatar_file = $request->file('avatar_img');
            $file_name = time().$avatar_file->getClientOriginalName();
            $file_path = 'user_avatar/'.$file_name;
            Storage::disk('s3')->put($file_path, file_get_contents($avatar_file));
        } else {
            $file_path = $user->avatar_img;
        }

        // 비밀번호
        if ($request->password) {

            $password_length = Str::length($request->password);

            if ($password_length < 6) {
                return response()->json([
                    'message' => '비밀번호는 6자리 이상 입력해 주세요.'
                ], 422);
            }

            $validate = $request->validate([
                'password' => 'string|min:6',
            ]);

            $is_same = Hash::check($validate['password'], Auth::user()->password);

            if ($is_same) {
                return response()->json(['message' => '이전 비밀번호는 사용할 수 없습니다.'], 422);
            }

            $new_password = Hash::make($validate['password']);
        }

        $existing_password = $user->password;

        $user->update([
            'name' => $request->name,
            'avatar_img' => $request->hasFile('avatar_img') ? env("AWS_URL").$file_path : $user->avatar_img,
            'password' => $new_password ?? $existing_password
        ]);

        $updated_user = $user->only('id', 'email', 'name', 'avatar_img');

        return response()->json([
            'data' => $updated_user,
            'message' => '유저 정보를 수정 하였습니다.'
        ], 200);
    }


    // 회원가입
    public function register(Request $request) {

        $valid_rules = [
            'email' => 'unique:users',
//            'name' => 'required|string|max:255',
//            'password' => 'required|string|min:6',
        ];

        $valid_messages = array(
//            'email.required'=> '이메일을 입력해 주세요.',
            'email.unique'=> '중복된 아아디 입니다. 다른 아이디로 입력해 주세요.',
//            'name.required'=> '이름을 입력해 주세요.',
//            'password.required' => '비밀번호를 입력해 주세요.',
//            'password.min' => '비밀번호는 6자리 이상 입력해 주세요.',
        );

        $register_validation = Validator::make($request->only( 'email'), $valid_rules, $valid_messages);

        // 필수 입력 값들에 대한 유효성 검사
        if ($register_validation->fails()) {
            return response()->json([
                'message' => $register_validation->errors()
            ], 422);
        }

        $data = request()->only('email', 'name', 'password', 'avatar_img');

        if ($request->hasFile('avatar_img')) {
            $avatar_file = $request->file('avatar_img');
            $file_name = time().$avatar_file->getClientOriginalName();
            $file_path = 'user_avatar/'.$file_name;
            Storage::disk('s3')->put($file_path, file_get_contents($avatar_file));
        } else {
            $file_path = null;
        }

        // 사용자 생성
        $user = User::create([
           'name' => $data['name'],
           'email' => $data['email'],
           'password' => bcrypt($data['password']),
           'avatar_img' => $file_path ? env("AWS_URL").$file_path : ""
        ]);

        $token = $user->createToken('access-token');
        return response([
           'token' => $token->accessToken,
           'message' => '회원가입을 축하드립니다.'
        ], 201);
    }


    public function login(Request $request) {
        $user = User::where('email', $request->user_id)->first();

        abort_unless($user, 403, '이메일 또는 비밀번호를 다시 확인해 주세요.');
        abort_unless(
            Hash::check(request('password'), $user->password),
            403,
            '이메일 또는 비밀번호를 다시 확인해 주세요!'
        );

        $token = $user->createToken('access-token');
        $logged_in_user = $user->only('id', 'name', 'email', 'avatar_img');

        return response([
            'token' => $token,
            'user' => $logged_in_user,
            'message' => '로그인 되었습니다.'
        ], 200);
    }


    public function logout(Request $request) {
        Auth::guard('api')->user()->tokens()->first()->revoke();
        return response()->json([
            'message' => '로그아웃 되었습니다.'
        ]);
    }
}
