<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function response($data, $code = 200)
    {
        return response()->json($data, $code);
    }

    protected function responseError($message, $errors = [], $code = 400)
    {
        $response = ['message' => $message];
        if (count($errors) > 0) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $code);
    }

    protected function responseInternalError($message, $code = 500)
    {
        return response()->json(['message' => $message], $code);
    }

    protected function responsePaginate($data, $total = null, $page = 1)
    {
        if (!isset($total)) {
            $total = count($data);
        }
        return $this->response([
            'rows' => $data,
            'total' => $total,
            'page' => $page,
        ]);
    }
}
